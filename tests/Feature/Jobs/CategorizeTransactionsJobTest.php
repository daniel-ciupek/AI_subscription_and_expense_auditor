<?php

declare(strict_types=1);

use App\Contracts\AiCategorizerInterface;
use App\DTOs\AiCategorization as AiCategorizationDto;
use App\Jobs\CategorizeTransactionsJob;
use App\Models\AiCategorization;
use App\Models\Import;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AiCategorizers\FakeAiCategorizer;
use Database\Seeders\CategorySeeder;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(CategorySeeder::class);
    $this->user = User::factory()->create();
    $this->import = Import::factory()->for($this->user)->create();
});

it('uses the Batchable trait so it can be dispatched inside a Bus::batch', function () {
    expect(in_array(Batchable::class, class_uses_recursive(CategorizeTransactionsJob::class), true))
        ->toBeTrue();
});

function makeTransaction(int $userId, int $importId, string $description, string $amount): Transaction
{
    return Transaction::create([
        'user_id' => $userId,
        'import_id' => $importId,
        'posted_at' => '2026-04-29',
        'amount' => $amount,
        'currency' => 'PLN',
        'description' => $description,
        'counterparty' => null,
        'balance' => null,
        'hash' => hash('sha256', $description.'|'.$amount.'|'.uniqid('', true)),
    ]);
}

it('categorizes a chunk and writes audit rows + category_id', function () {
    $netflix = makeTransaction($this->user->id, $this->import->id, 'NETFLIX SUBSCRIPTION', '-49.99');
    $biedronka = makeTransaction($this->user->id, $this->import->id, 'BIEDRONKA 1234 POZNAN', '-87.40');

    (new CategorizeTransactionsJob([$netflix->id, $biedronka->id]))
        ->handle(new FakeAiCategorizer);

    expect($netflix->fresh()->category?->slug)->toBe('subscriptions');
    expect($biedronka->fresh()->category?->slug)->toBe('food');
    expect(AiCategorization::count())->toBe(2);

    $audit = AiCategorization::where('transaction_id', $netflix->id)->first();
    expect($audit->ai_prompt_version)->toBe('fake-v1');
    expect((float) $audit->confidence)->toBe(0.95);
});

it('falls back to "other" category when slug is unknown', function () {
    $tx = makeTransaction($this->user->id, $this->import->id, 'something weird abc', '-10.00');

    $stub = new class implements AiCategorizerInterface
    {
        public function version(): string
        {
            return 'stub-v1';
        }

        public function categorize(iterable $transactions): array
        {
            $results = [];
            foreach ($transactions as $tx) {
                $results[$tx->id] = new AiCategorizationDto(
                    transactionId: $tx->id,
                    categorySlug: 'no-such-slug',
                    confidence: 0.1,
                    rawResponse: ['driver' => 'stub'],
                );
            }

            return $results;
        }
    };

    (new CategorizeTransactionsJob([$tx->id]))->handle($stub);

    expect($tx->fresh()->category?->slug)->toBe('other');
});

it('serves cached results without calling the categorizer again', function () {
    $tx = makeTransaction($this->user->id, $this->import->id, 'NETFLIX SUBSCRIPTION', '-49.99');

    Cache::put(
        CategorizeTransactionsJob::cacheKey($tx->description, $tx->amount),
        ['slug' => 'subscriptions', 'confidence' => 0.99, 'version' => 'fake-v1'],
        now()->addDay(),
    );

    $spy = new class implements AiCategorizerInterface
    {
        public int $calls = 0;

        public function version(): string
        {
            return 'fake-v1';
        }

        public function categorize(iterable $transactions): array
        {
            $this->calls++;

            return [];
        }
    };

    (new CategorizeTransactionsJob([$tx->id]))->handle($spy);

    expect($spy->calls)->toBe(0);
    expect($tx->fresh()->category?->slug)->toBe('subscriptions');

    $audit = AiCategorization::where('transaction_id', $tx->id)->first();
    expect($audit->raw_response)->toBe(['driver' => 'cache', 'cached' => true]);
});

it('ignores cached results from a different prompt version', function () {
    $tx = makeTransaction($this->user->id, $this->import->id, 'NETFLIX SUBSCRIPTION', '-49.99');

    Cache::put(
        CategorizeTransactionsJob::cacheKey($tx->description, $tx->amount),
        ['slug' => 'food', 'confidence' => 0.99, 'version' => 'old-prompt'],
        now()->addDay(),
    );

    (new CategorizeTransactionsJob([$tx->id]))->handle(new FakeAiCategorizer);

    expect($tx->fresh()->category?->slug)->toBe('subscriptions');
});

it('writes fresh results to the cache for future runs', function () {
    $tx = makeTransaction($this->user->id, $this->import->id, 'NETFLIX SUBSCRIPTION', '-49.99');

    (new CategorizeTransactionsJob([$tx->id]))->handle(new FakeAiCategorizer);

    $cached = Cache::get(CategorizeTransactionsJob::cacheKey($tx->description, $tx->amount));
    expect($cached)->toMatchArray([
        'slug' => 'subscriptions',
        'version' => 'fake-v1',
    ]);
});

it('uses the same cache key regardless of digit noise in descriptions', function () {
    $a = CategorizeTransactionsJob::cacheKey('BIEDRONKA 12345', '-50.00');
    $b = CategorizeTransactionsJob::cacheKey('BIEDRONKA 99999', '-87.40');

    expect($a)->toBe($b);
});

it('uses different cache keys for opposite-sign transactions', function () {
    $charge = CategorizeTransactionsJob::cacheKey('NETFLIX', '-49.99');
    $refund = CategorizeTransactionsJob::cacheKey('NETFLIX', '49.99');

    expect($charge)->not->toBe($refund);
});

it('does nothing when given an empty list', function () {
    (new CategorizeTransactionsJob([]))->handle(new FakeAiCategorizer);

    expect(AiCategorization::count())->toBe(0);
});
