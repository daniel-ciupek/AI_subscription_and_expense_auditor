<?php

declare(strict_types=1);

namespace App\Services\AiCategorizers;

use App\Contracts\AiCategorizerInterface;
use App\DTOs\AiCategorization;
use App\DTOs\TransactionForCategorization;

/**
 * Deterministic, offline categorizer used in tests and in local dev when no
 * Groq key is configured. Maps a curated list of Polish + global merchant
 * keywords to category slugs that exist in CategorySeeder.
 *
 * Keep this in sync with category slugs seeded by CategorySeeder so the
 * downstream Job can resolve the slug → category_id without an extra round-trip.
 */
class FakeAiCategorizer implements AiCategorizerInterface
{
    public function __construct(
        private readonly int $latencyMs = 0,
    ) {}

    public function version(): string
    {
        return 'fake-v1';
    }

    public function categorize(iterable $transactions): array
    {
        if ($this->latencyMs > 0) {
            usleep($this->latencyMs * 1000);
        }

        $results = [];
        foreach ($transactions as $tx) {
            $slug = $this->matchSlug($tx);
            $confidence = $slug === 'other' ? 0.40 : 0.95;

            $results[$tx->id] = new AiCategorization(
                transactionId: $tx->id,
                categorySlug: $slug,
                confidence: $confidence,
                rawResponse: [
                    'driver' => 'fake',
                    'category_slug' => $slug,
                    'matched_keyword' => $this->lastMatchedKeyword,
                ],
            );
        }

        return $results;
    }

    private ?string $lastMatchedKeyword = null;

    private function matchSlug(TransactionForCategorization $tx): string
    {
        $haystack = mb_strtolower($tx->description);

        foreach (self::keywordMap() as $slug => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    $this->lastMatchedKeyword = $keyword;

                    return $slug;
                }
            }
        }

        // Positive amount with no keyword hit → most often a salary / refund.
        if (! $tx->isExpense()) {
            $this->lastMatchedKeyword = '(positive amount)';

            return 'salary';
        }

        $this->lastMatchedKeyword = null;

        return 'other';
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function keywordMap(): array
    {
        return [
            'subscriptions' => [
                'netflix', 'spotify', 'hbo', 'disney', 'apple.com', 'icloud',
                'google ', 'youtube', 'amazon prime', 'allegro lite', 'openai',
                'chatgpt', 'github', 'jetbrains', 'office 365', 'microsoft 365',
                'canva', 'notion', 'figma',
            ],
            'food' => [
                'biedronka', 'lidl', 'żabka', 'zabka', 'auchan', 'kaufland',
                'carrefour', 'tesco', 'dino', 'rossmann', 'mcdonald', 'kfc',
                'burger king', 'pizza', 'restauracja', 'glovo', 'uber eats',
                'pyszne', 'bolt food', 'wolt', 'piekarnia',
            ],
            'transport' => [
                'uber', 'bolt', 'freenow', 'mpk', 'ztm', 'kolej', 'intercity',
                'pkp', 'orlen', ' bp ', 'shell', 'circle k', 'lotos', 'parking',
                'autostrada', 'taxi',
            ],
            'entertainment' => [
                'kino', 'cinema', 'helios', 'multikino', 'steam', 'epic games',
                'playstation', 'xbox', 'nintendo', 'spotify family', 'theatre',
                'teatr', 'filharmonia',
            ],
            'bills' => [
                'orange', 'plus ', 't-mobile', 'play ', 'netia', ' upc ',
                'energa', 'tauron', 'pge', 'enea', 'pgnig', 'gaz ', 'rachunek',
                'czynsz', 'wodociąg', 'wspólnota',
            ],
            'salary' => [
                'wynagrodzenie', 'pensja', 'salary', 'wyplata', 'wypłata',
            ],
        ];
    }
}
