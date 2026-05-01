<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\DemoSeeder;

it('seeds a demo user with categorized transactions and subscriptions', function () {
    $this->seed(DemoSeeder::class);

    $user = User::query()->where('email', 'demo@example.com')->firstOrFail();

    expect($user->name)->toBe('Demo User');
    expect($user->transactions()->count())->toBeGreaterThan(100);
    expect($user->imports()->count())->toBe(1);

    // FakeAiCategorizer hits well-known merchants → most rows get a category.
    $categorized = $user->transactions()->whereNotNull('category_id')->count();
    expect($categorized)->toBeGreaterThan(50);

    // Detector should pick up the recurring monthly merchants.
    expect($user->subscriptions()->count())->toBeGreaterThanOrEqual(5);

    // The seeded NETFLIX EU PREMIUM duplicates NETFLIX SUBSCRIPTION → at
    // least one subscription must carry is_duplicate_of_id.
    expect(Subscription::query()->whereNotNull('is_duplicate_of_id')->count())
        ->toBeGreaterThanOrEqual(1);
});

it('is idempotent — re-seeding wipes prior demo data and rebuilds it', function () {
    $this->seed(DemoSeeder::class);
    $firstCount = User::query()
        ->where('email', 'demo@example.com')
        ->firstOrFail()
        ->transactions()
        ->count();

    $this->seed(DemoSeeder::class);
    $user = User::query()->where('email', 'demo@example.com')->firstOrFail();

    // Same demo user, NOT a second one.
    expect(User::query()->where('email', 'demo@example.com')->count())->toBe(1);
    // Transaction count is in the same ballpark — exact match isn't possible
    // because some buckets use random_int — but it should be the same order
    // of magnitude (within 10%).
    expect(abs($user->transactions()->count() - $firstCount))
        ->toBeLessThan((int) ($firstCount * 0.1));
});
