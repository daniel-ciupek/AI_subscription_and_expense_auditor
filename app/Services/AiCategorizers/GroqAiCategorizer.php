<?php

declare(strict_types=1);

namespace App\Services\AiCategorizers;

use App\Contracts\AiCategorizerInterface;
use App\DTOs\AiCategorization;
use App\DTOs\TransactionForCategorization;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use JsonException;

/**
 * Groq-backed categorizer. Sends a batch of transactions in a single chat
 * completion request, requires JSON response format, validates the shape
 * locally, and falls back to category "other" on any deviation rather than
 * letting hallucinated slugs leak into the database.
 */
class GroqAiCategorizer implements AiCategorizerInterface
{
    private const PROMPT_VERSION = 'groq-llama-3.3-70b-v1';

    /**
     * @var array<int, string>
     */
    private const ALLOWED_SLUGS = [
        'subscriptions', 'food', 'transport', 'entertainment',
        'bills', 'salary', 'other',
    ];

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

    public function categorize(iterable $transactions): array
    {
        $items = array_values($transactions instanceof \Traversable
            ? iterator_to_array($transactions, false)
            : $transactions);

        if ($items === []) {
            return [];
        }

        $payload = $this->buildPayload($items);

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->retry(3, function (int $attempt): int {
                    return min((int) (2 ** ($attempt - 1) * 1000), 4000);
                }, throw: false)
                ->post($this->baseUrl.'/chat/completions', $payload);
        } catch (ConnectionException $e) {
            Log::warning('Groq categorizer: connection failed', [
                'error' => $e->getMessage(),
                'count' => count($items),
            ]);

            return $this->fallback($items, ['error' => 'connection_failed']);
        }

        if (! $response->successful()) {
            Log::warning('Groq categorizer: non-2xx response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->fallback($items, ['error' => 'http_'.$response->status()]);
        }

        $body = $response->json();
        $content = data_get($body, 'choices.0.message.content');
        if (! is_string($content)) {
            return $this->fallback($items, ['error' => 'no_content', 'response' => $body]);
        }

        try {
            $decoded = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::warning('Groq categorizer: invalid JSON', ['error' => $e->getMessage()]);

            return $this->fallback($items, ['error' => 'invalid_json', 'content' => $content]);
        }

        if (! is_array($decoded) || ! $this->passesSchema($decoded)) {
            return $this->fallback($items, ['error' => 'schema_violation', 'decoded' => $decoded]);
        }

        /** @var array<int, array{id: int, category_slug: string, confidence: float|int|string}> $entries */
        $entries = $decoded['categorizations'];

        return $this->mapResults($entries, $items, $body);
    }

    /**
     * @param  array<int, TransactionForCategorization>  $items
     * @return array<string, mixed>
     */
    private function buildPayload(array $items): array
    {
        $userPayload = array_map(
            fn (TransactionForCategorization $tx): array => [
                'id' => $tx->id,
                'description' => $tx->description,
                'amount' => $tx->amount,
            ],
            $items,
        );

        return [
            'model' => $this->model,
            'temperature' => 0.1,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => json_encode([
                    'transactions' => $userPayload,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
            ],
        ];
    }

    private function systemPrompt(): string
    {
        $allowed = implode(', ', self::ALLOWED_SLUGS);

        return <<<PROMPT
            You are a financial transaction categorizer. Respond with ONE JSON
            object that matches this exact schema and nothing else:

            {"categorizations": [{"id": <int>, "category_slug": <string>, "confidence": <float between 0 and 1>}]}

            Allowed category_slug values (case-sensitive): {$allowed}.

            Guidelines:
            - subscriptions: recurring SaaS, streaming, digital services
            - food: groceries, restaurants, food delivery
            - transport: fuel, taxi, rideshare, public transit, parking, tolls
            - entertainment: cinema, games, music, theater, events
            - bills: utilities, telecom, internet, rent, insurance
            - salary: incoming wages or salary payments
            - other: only when no other category clearly fits

            Return one item per provided transaction id, in any order. Do not
            invent ids that were not provided. Bank descriptions may be in
            Polish — handle them naturally.
            PROMPT;
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function passesSchema(array $decoded): bool
    {
        $allowed = implode(',', self::ALLOWED_SLUGS);

        $validator = Validator::make($decoded, [
            'categorizations' => 'required|array|min:1',
            'categorizations.*.id' => 'required|integer',
            'categorizations.*.category_slug' => "required|string|in:{$allowed}",
            'categorizations.*.confidence' => 'required|numeric|between:0,1',
        ]);

        return ! $validator->fails();
    }

    /**
     * @param  array<int, array{id: int, category_slug: string, confidence: float|int|string}>  $entries
     * @param  array<int, TransactionForCategorization>  $items
     * @param  array<string, mixed>|null  $rawBody
     * @return array<int, AiCategorization>
     */
    private function mapResults(array $entries, array $items, ?array $rawBody): array
    {
        $byId = [];
        foreach ($entries as $entry) {
            $byId[(int) $entry['id']] = [
                'slug' => $entry['category_slug'],
                'confidence' => (float) $entry['confidence'],
            ];
        }

        $results = [];
        foreach ($items as $tx) {
            if (! isset($byId[$tx->id])) {
                $results[$tx->id] = $this->otherCategorization($tx->id, [
                    'reason' => 'missing_in_response',
                    'response' => $rawBody,
                ]);

                continue;
            }

            $results[$tx->id] = new AiCategorization(
                transactionId: $tx->id,
                categorySlug: $byId[$tx->id]['slug'],
                confidence: $byId[$tx->id]['confidence'],
                rawResponse: [
                    'driver' => 'groq',
                    'model' => $this->model,
                    'response' => $rawBody,
                ],
            );
        }

        return $results;
    }

    /**
     * @param  array<int, TransactionForCategorization>  $items
     * @param  array<string, mixed>  $context
     * @return array<int, AiCategorization>
     */
    private function fallback(array $items, array $context): array
    {
        $results = [];
        foreach ($items as $tx) {
            $results[$tx->id] = $this->otherCategorization($tx->id, $context);
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function otherCategorization(int $transactionId, array $context): AiCategorization
    {
        return new AiCategorization(
            transactionId: $transactionId,
            categorySlug: 'other',
            confidence: 0.0,
            rawResponse: array_merge(['driver' => 'groq', 'fallback' => true], $context),
        );
    }
}
