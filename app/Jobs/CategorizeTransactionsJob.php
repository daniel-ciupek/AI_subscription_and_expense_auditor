<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\AiCategorizerInterface;
use App\DTOs\TransactionForCategorization;
use App\Models\AiCategorization;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\TransactionNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Categorize a chunk of up to MAX_BATCH transactions in a single AI call.
 * Cache hits short-circuit the AI request; fresh results are written back to
 * the cache and an ai_categorizations audit row is persisted for every
 * transaction (cached or fresh) so the prompt-version trail is complete.
 */
class CategorizeTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const MAX_BATCH = 20;

    public const CACHE_TTL_DAYS = 30;

    public int $tries = 1;

    /**
     * @param  array<int, int>  $transactionIds
     */
    public function __construct(public readonly array $transactionIds) {}

    public function handle(AiCategorizerInterface $categorizer): void
    {
        if ($this->transactionIds === []) {
            return;
        }

        $transactions = Transaction::query()
            ->whereIn('id', $this->transactionIds)
            ->get();

        if ($transactions->isEmpty()) {
            return;
        }

        $version = $categorizer->version();
        /** @var array<string, int> $categoryIdsBySlug */
        $categoryIdsBySlug = Category::query()->pluck('id', 'slug')->all();

        /** @var array<int, array{slug: string, confidence: float, source: string}> $resolved */
        $resolved = [];
        /** @var array<int, string> $cacheKeys */
        $cacheKeys = [];
        /** @var array<int, TransactionForCategorization> $toCategorize */
        $toCategorize = [];

        foreach ($transactions as $tx) {
            $key = self::cacheKey($tx->description, $tx->amount);
            $cacheKeys[$tx->id] = $key;

            $hit = Cache::get($key);
            if ($this->isCacheHit($hit, $version)) {
                $resolved[$tx->id] = [
                    'slug' => $hit['slug'],
                    'confidence' => (float) $hit['confidence'],
                    'source' => 'cache',
                ];

                continue;
            }

            $toCategorize[$tx->id] = new TransactionForCategorization(
                id: $tx->id,
                description: $tx->description,
                amount: $tx->amount,
            );
        }

        $aiResults = $toCategorize === []
            ? []
            : $categorizer->categorize(array_values($toCategorize));

        foreach ($transactions as $tx) {
            if (isset($resolved[$tx->id])) {
                $slug = $resolved[$tx->id]['slug'];
                $confidence = $resolved[$tx->id]['confidence'];
                $rawResponse = ['driver' => 'cache', 'cached' => true];
            } elseif (isset($aiResults[$tx->id])) {
                $result = $aiResults[$tx->id];
                $slug = $result->categorySlug;
                $confidence = $result->confidence;
                $rawResponse = $result->rawResponse;

                Cache::put(
                    $cacheKeys[$tx->id],
                    [
                        'slug' => $slug,
                        'confidence' => $confidence,
                        'version' => $version,
                    ],
                    now()->addDays(self::CACHE_TTL_DAYS),
                );
            } else {
                Log::warning('CategorizeTransactionsJob: missing result for transaction', [
                    'transaction_id' => $tx->id,
                ]);

                continue;
            }

            $categoryId = $categoryIdsBySlug[$slug]
                ?? $categoryIdsBySlug['other']
                ?? null;

            AiCategorization::create([
                'transaction_id' => $tx->id,
                'category_id' => $categoryId,
                'confidence' => $confidence,
                'ai_prompt_version' => $version,
                'raw_response' => $rawResponse,
            ]);

            $tx->update(['category_id' => $categoryId]);
        }
    }

    /**
     * Build the cache key from a normalized description plus the sign of
     * the amount. Two transactions with identical descriptions but opposite
     * signs (e.g. a charge vs. a refund) must NOT share a cached category.
     */
    public static function cacheKey(string $description, string $amount): string
    {
        $sign = (float) $amount < 0 ? '-' : '+';
        $normalized = TransactionNormalizer::normalize($description);

        return 'ai_cat:'.hash('sha256', $normalized.'|'.$sign);
    }

    /**
     * @param  mixed  $hit
     */
    private function isCacheHit($hit, string $version): bool
    {
        return is_array($hit)
            && isset($hit['slug'], $hit['confidence'], $hit['version'])
            && $hit['version'] === $version;
    }
}
