<?php

declare(strict_types=1);

use App\Actions\DetectSubscriptionsAction;
use App\Models\Category;
use App\Models\Import;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;

beforeEach(function () {
    $this->seed(CategorySeeder::class);
    $this->user = User::factory()->create();
    $this->import = Import::factory()->for($this->user)->create();
});

function makeTx(int $userId, int $importId, string $description, string $amount, string $postedAt, ?int $categoryId = null): Transaction
{
    return Transaction::create([
        'user_id' => $userId,
        'import_id' => $importId,
        'category_id' => $categoryId,
        'posted_at' => $postedAt,
        'amount' => $amount,
        'currency' => 'PLN',
        'description' => $description,
        'counterparty' => null,
        'balance' => null,
        'hash' => hash('sha256', $description.'|'.$amount.'|'.$postedAt.'|'.uniqid('', true)),
    ]);
}

it('detects a monthly subscription from three consistent charges', function () {
    $today = CarbonImmutable::now();
    $subsCat = Category::query()->where('slug', 'subscriptions')->firstOrFail();

    foreach ([90, 60, 30] as $i => $daysAgo) {
        makeTx(
            $this->user->id,
            $this->import->id,
            'NETFLIX SUBSCRIPTION',
            '-49.99',
            $today->subDays($daysAgo)->toDateString(),
            $subsCat->id,
        );
    }

    $detected = (new DetectSubscriptionsAction)->handle($this->user);

    expect($detected)->toHaveCount(1);
    expect(Subscription::count())->toBe(1);

    $sub = Subscription::first();
    expect($sub->name)->toBe('NETFLIX SUBSCRIPTION');
    expect((float) $sub->amount)->toBe(49.99);
    expect($sub->billing_cycle_days)->toBe(30);
    expect($sub->category_id)->toBe($subsCat->id);
    expect($sub->last_charge_at->toDateString())->toBe($today->subDays(30)->toDateString());
    expect($sub->next_expected_charge_at->toDateString())
        ->toBe($today->subDays(30)->addDays(30)->toDateString());
});

it('groups transactions whose descriptions differ only by digit noise', function () {
    $today = CarbonImmutable::now();

    foreach ([90, 60, 30] as $i => $daysAgo) {
        makeTx(
            $this->user->id,
            $this->import->id,
            'BIEDRONKA '.($i + 1).str_pad((string) $i, 4, '0', STR_PAD_LEFT).' POZNAN',
            '-87.40',
            $today->subDays($daysAgo)->toDateString(),
        );
    }

    $detected = (new DetectSubscriptionsAction)->handle($this->user);

    expect($detected)->toHaveCount(1);
});

it('rejects groups with inconsistent amounts', function () {
    $today = CarbonImmutable::now();

    makeTx($this->user->id, $this->import->id, 'BIEDRONKA POZNAN', '-50.00', $today->subDays(90)->toDateString());
    makeTx($this->user->id, $this->import->id, 'BIEDRONKA POZNAN', '-150.00', $today->subDays(60)->toDateString());
    makeTx($this->user->id, $this->import->id, 'BIEDRONKA POZNAN', '-200.00', $today->subDays(30)->toDateString());

    expect((new DetectSubscriptionsAction)->handle($this->user))->toBe([]);
    expect(Subscription::count())->toBe(0);
});

it('rejects groups whose cycle is outside the monthly window', function () {
    $today = CarbonImmutable::now();

    // Weekly grocery — too short.
    foreach ([21, 14, 7] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'LIDL', '-100.00', $today->subDays($daysAgo)->toDateString());
    }

    expect((new DetectSubscriptionsAction)->handle($this->user))->toBe([]);
});

it('skips one-off transactions', function () {
    $today = CarbonImmutable::now();
    makeTx($this->user->id, $this->import->id, 'IKEA POZNAN', '-1234.50', $today->subDays(30)->toDateString());

    expect((new DetectSubscriptionsAction)->handle($this->user))->toBe([]);
});

it('is idempotent — re-running upserts the same subscription', function () {
    $today = CarbonImmutable::now();

    foreach ([90, 60, 30] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'SPOTIFY PREMIUM', '-23.99', $today->subDays($daysAgo)->toDateString());
    }

    (new DetectSubscriptionsAction)->handle($this->user);
    (new DetectSubscriptionsAction)->handle($this->user);

    expect(Subscription::count())->toBe(1);
});

it('ignores income transactions', function () {
    $today = CarbonImmutable::now();
    foreach ([90, 60, 30] as $daysAgo) {
        // Positive amount → income, must not become a subscription even if recurring.
        makeTx($this->user->id, $this->import->id, 'WYNAGRODZENIE ACME', '5000.00', $today->subDays($daysAgo)->toDateString());
    }

    expect((new DetectSubscriptionsAction)->handle($this->user))->toBe([]);
});

it('ignores transactions older than the lookback window', function () {
    $today = CarbonImmutable::now();
    foreach ([400, 370, 340] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'NETFLIX', '-49.99', $today->subDays($daysAgo)->toDateString());
    }

    expect((new DetectSubscriptionsAction)->handle($this->user))->toBe([]);
});

it('inherits the most common category from the underlying transactions', function () {
    $today = CarbonImmutable::now();
    $subs = Category::query()->where('slug', 'subscriptions')->firstOrFail();
    $other = Category::query()->where('slug', 'other')->firstOrFail();

    makeTx($this->user->id, $this->import->id, 'NETFLIX', '-49.99', $today->subDays(90)->toDateString(), $subs->id);
    makeTx($this->user->id, $this->import->id, 'NETFLIX', '-49.99', $today->subDays(60)->toDateString(), $subs->id);
    makeTx($this->user->id, $this->import->id, 'NETFLIX', '-49.99', $today->subDays(30)->toDateString(), $other->id);

    (new DetectSubscriptionsAction)->handle($this->user);

    expect(Subscription::first()->category_id)->toBe($subs->id);
});
