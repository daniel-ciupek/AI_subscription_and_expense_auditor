<?php

declare(strict_types=1);

use App\DTOs\TransactionForCategorization;
use App\Services\AiCategorizers\FakeAiCategorizer;

it('reports a stable prompt version', function () {
    $categorizer = new FakeAiCategorizer;

    expect($categorizer->version())->toBe('fake-v1');
});

it('maps merchant keywords to seeded category slugs', function (string $description, string $amount, string $expectedSlug) {
    $categorizer = new FakeAiCategorizer;
    $tx = new TransactionForCategorization(
        id: 1,
        description: $description,
        amount: $amount,
    );

    $result = $categorizer->categorize([$tx]);

    expect($result)->toHaveKey(1);
    expect($result[1]->categorySlug)->toBe($expectedSlug);
})->with([
    ['NETFLIX SUBSCRIPTION', '-49.99', 'subscriptions'],
    ['Spotify Premium PL', '-23.99', 'subscriptions'],
    ['BIEDRONKA 1234', '-87.40', 'food'],
    ['UBER TRIP HELP.UBER.COM', '-22.50', 'transport'],
    ['Cinema City Bonarka', '-45.00', 'entertainment'],
    ['ORANGE Polska faktura', '-65.00', 'bills'],
    ['WYNAGRODZENIE KWIECIEN', '5000.00', 'salary'],
]);

it('falls back to "salary" for unknown positive amounts and "other" for unknown expenses', function () {
    $categorizer = new FakeAiCategorizer;

    $unknownIncome = new TransactionForCategorization(2, 'Unknown sender 12345', '1500.00');
    $unknownExpense = new TransactionForCategorization(3, 'Mystery payment xyz', '-12.34');

    $results = $categorizer->categorize([$unknownIncome, $unknownExpense]);

    expect($results[2]->categorySlug)->toBe('salary');
    expect($results[3]->categorySlug)->toBe('other');
});

it('returns lower confidence for the "other" fallback', function () {
    $categorizer = new FakeAiCategorizer;

    $matched = new TransactionForCategorization(10, 'NETFLIX', '-49.99');
    $fallback = new TransactionForCategorization(11, 'random merchant abc123', '-10.00');

    $results = $categorizer->categorize([$matched, $fallback]);

    expect($results[10]->confidence)->toBeGreaterThan($results[11]->confidence);
    expect($results[11]->categorySlug)->toBe('other');
});

it('keys results by transaction id and preserves the raw response shape', function () {
    $categorizer = new FakeAiCategorizer;
    $tx = new TransactionForCategorization(42, 'NETFLIX SUBSCRIPTION', '-49.99');

    $results = $categorizer->categorize([$tx]);

    expect($results)->toHaveKey(42);
    expect($results[42]->transactionId)->toBe(42);
    expect($results[42]->rawResponse)
        ->toHaveKey('driver')
        ->toHaveKey('category_slug');
    expect($results[42]->rawResponse['driver'])->toBe('fake');
});
