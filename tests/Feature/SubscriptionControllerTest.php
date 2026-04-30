<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\CategorySeeder;

beforeEach(function () {
    $this->seed(CategorySeeder::class);
});

it('redirects guests to the login page', function () {
    $this->get(route('subscriptions.index'))->assertRedirect(route('login'));
});

it('renders the subscriptions index with an empty state for a fresh user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('subscriptions.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('Subscriptions/Index')
                ->where('subscriptions', [])
                ->where('monthlyTotal', 0),
        );
});

it('lists subscriptions with their category and a monthly cost estimate', function () {
    $user = User::factory()->create();
    $subs = Category::query()->where('slug', 'subscriptions')->firstOrFail();

    Subscription::create([
        'user_id' => $user->id,
        'category_id' => $subs->id,
        'name' => 'NETFLIX SUBSCRIPTION',
        'amount' => '49.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-01',
        'next_expected_charge_at' => '2026-05-01',
    ]);
    Subscription::create([
        'user_id' => $user->id,
        'category_id' => $subs->id,
        'name' => 'SPOTIFY PREMIUM',
        'amount' => '23.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-15',
        'next_expected_charge_at' => '2026-05-15',
    ]);

    $this->actingAs($user)
        ->get(route('subscriptions.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('Subscriptions/Index')
                ->has('subscriptions', 2)
                // Sorted by amount desc — Netflix first.
                ->where('subscriptions.0.name', 'NETFLIX SUBSCRIPTION')
                ->where('subscriptions.0.amount', 49.99)
                ->where('subscriptions.0.category.slug', 'subscriptions')
                ->where('subscriptions.1.name', 'SPOTIFY PREMIUM')
                ->where('monthlyTotal', 73.98),
        );
});

it('does not leak another user\'s subscriptions', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Subscription::create([
        'user_id' => $bob->id,
        'name' => 'BOBs NETFLIX',
        'amount' => '49.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-01',
        'next_expected_charge_at' => '2026-05-01',
    ]);

    $this->actingAs($alice)
        ->get(route('subscriptions.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('Subscriptions/Index')
                ->where('subscriptions', []),
        );
});
