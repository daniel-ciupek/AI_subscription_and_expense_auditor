<?php

declare(strict_types=1);

namespace App\Services\AiSubscriptionDetectors;

use App\Contracts\AiSubscriptionDetectorInterface;
use App\DTOs\AiSubscriptionDetection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use JsonException;

/**
 * DeepSeek-backed subscription detector. Mirrors the categorizer driver
 * shape (OpenAI-compatible chat completions, JSON-only response) but
 * answers a different question: given a small group of transactions
 * sharing a merchant key, is this a recurring subscription?
 *
 * The model is forced to pick a billing cycle from the same set of
 * windows the rule engine accepts, so we can't end up with a "every
 * 47 days" subscription in the database.
 */
class DeepseekAiSubscriptionDetector implements AiSubscriptionDetectorInterface
{
    private const PROMPT_VERSION = 'deepseek-subdetect-v1';

    /**
     * Cycle days the model is allowed to return. Aligned with
     * DetectSubscriptionsAction::CYCLE_WINDOWS so post-validation never
     * has to massage the value.
     *
     * @var list<int>
     */
    private const ALLOWED_CYCLE_DAYS = [7, 14, 30, 90, 365];

    private const CACHE_TTL_SECONDS = 7 * 24 * 60 * 60;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl,
        private readonly int $timeoutSeconds = 30,
    ) {}

    public function version(): string
    {
        return self::PROMPT_VERSION;
    }

    public function detect(array $transactions): ?AiSubscriptionDetection
    {
        if ($transactions === []) {
            return null;
        }

        $cacheKey = $this->cacheKey($transactions);

        // Wrap the value so a cached "no detection" (null) is distinguishable
        // from a cache miss — Cache::has() reports null entries as missing
        // on some drivers.
        /** @var array{value: AiSubscriptionDetection|null}|null $cached */
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached['value'];
        }

        $result = $this->callApi($transactions);
        Cache::put($cacheKey, ['value' => $result], self::CACHE_TTL_SECONDS);

        return $result;
    }

    /**
     * @param  list<array{posted_at: string, amount: float, description: string}>  $transactions
     */
    private function callApi(array $transactions): ?AiSubscriptionDetection
    {
        $payload = $this->buildPayload($transactions);

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->retry(3, function (int $attempt): int {
                    return min((int) (2 ** ($attempt - 1) * 1000), 4000);
                }, throw: false)
                ->post($this->baseUrl.'/chat/completions', $payload);
        } catch (ConnectionException $e) {
            Log::warning('Deepseek subdetector: connection failed', [
                'error' => $e->getMessage(),
                'count' => count($transactions),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Deepseek subdetector: non-2xx response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $body = $response->json();
        $content = data_get($body, 'choices.0.message.content');
        if (! is_string($content)) {
            return null;
        }

        try {
            $decoded = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::warning('Deepseek subdetector: invalid JSON', ['error' => $e->getMessage()]);

            return null;
        }

        if (! is_array($decoded) || ! $this->passesSchema($decoded)) {
            return null;
        }

        if ($decoded['is_subscription'] !== true) {
            return null;
        }

        return new AiSubscriptionDetection(
            name: (string) $decoded['name'],
            billingCycleDays: (int) $decoded['billing_cycle_days'],
            amount: (float) $decoded['expected_amount'],
            currency: (string) ($decoded['currency'] ?? 'PLN'),
            confidence: (float) $decoded['confidence'],
            rawResponse: [
                'driver' => 'deepseek',
                'model' => $this->model,
                'response' => $body,
            ],
        );
    }

    /**
     * @param  list<array{posted_at: string, amount: float, description: string}>  $transactions
     * @return array<string, mixed>
     */
    private function buildPayload(array $transactions): array
    {
        return [
            'model' => $this->model,
            'temperature' => 0.1,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => json_encode([
                    'transactions' => $transactions,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
            ],
        ];
    }

    private function systemPrompt(): string
    {
        $cycles = implode(', ', self::ALLOWED_CYCLE_DAYS);

        return <<<PROMPT
            You inspect bank transactions for the SAME merchant and decide
            whether they form a recurring subscription. Respond with ONE JSON
            object that matches this exact schema and nothing else:

            {
              "is_subscription": <bool>,
              "name": <string>,
              "billing_cycle_days": <int — must be one of: {$cycles}>,
              "expected_amount": <number, positive>,
              "currency": <string, ISO 4217, default "PLN">,
              "confidence": <float between 0 and 1>
            }

            Guidelines:
            - Subscriptions: streaming, SaaS, telecom, gym, cloud storage,
              insurance, magazines, domains, anything that auto-charges.
            - NOT subscriptions: groceries (even if weekly), ad-hoc shopping,
              salary, transfers, refunds, ATM withdrawals, restaurants.
            - If the cadence is irregular but the merchant clearly auto-bills
              (e.g., one missed month), still call it a subscription and pick
              the closest allowed cycle.
            - If you are not confident, set is_subscription=false and use
              confidence below 0.5 — the caller will reject the suggestion.
            - Polish bank statements are common — read descriptions naturally.
            PROMPT;
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function passesSchema(array $decoded): bool
    {
        $allowedCycles = implode(',', self::ALLOWED_CYCLE_DAYS);

        $validator = Validator::make($decoded, [
            'is_subscription' => 'required|boolean',
            'name' => 'required_if:is_subscription,true|string|max:200',
            'billing_cycle_days' => "required_if:is_subscription,true|integer|in:{$allowedCycles}",
            'expected_amount' => 'required_if:is_subscription,true|numeric|gt:0',
            'currency' => 'sometimes|string|size:3',
            'confidence' => 'required|numeric|between:0,1',
        ]);

        return ! $validator->fails();
    }

    /**
     * Cache by hash of (description, amount, posted_at) tuples — same
     * group → same answer, regardless of order. Reset when prompt
     * version changes.
     *
     * @param  list<array{posted_at: string, amount: float, description: string}>  $transactions
     */
    private function cacheKey(array $transactions): string
    {
        $tuples = array_map(
            static fn (array $tx): string => sprintf(
                '%s|%.2f|%s',
                $tx['posted_at'],
                $tx['amount'],
                $tx['description'],
            ),
            $transactions,
        );
        sort($tuples);

        return 'subdetect:'.self::PROMPT_VERSION.':'.hash('sha256', implode("\n", $tuples));
    }
}
