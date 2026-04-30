<?php

declare(strict_types=1);

use App\Jobs\DetectSubscriptionsJob;
use App\Models\Category;
use App\Models\Import;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\Bus;

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
                ->where('monthlyTotal', 0)
                ->where('duplicateCount', 0)
                ->where('transactionsCount', 0),
        );
});

it('exposes the user transaction count so the empty state can adapt', function () {
    $user = User::factory()->create();
    $import = Import::factory()->for($user)->create();

    foreach (range(1, 5) as $i) {
        Transaction::create([
            'user_id' => $user->id,
            'import_id' => $import->id,
            'posted_at' => now()->subDays($i),
            'amount' => '-10.00',
            'currency' => 'PLN',
            'description' => "tx {$i}",
            'counterparty' => null,
            'balance' => null,
            'hash' => hash('sha256', "tx-{$i}"),
        ]);
    }

    $this->actingAs($user)
        ->get(route('subscriptions.index'))
        ->assertInertia(
            fn ($page) => $page->component('Subscriptions/Index')
                ->where('subscriptions', [])
                ->where('transactionsCount', 5),
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

it('keeps the statement amount for monthly-window cycles (28-32 days)', function () {
    $user = User::factory()->create();

    Subscription::create([
        'user_id' => $user->id,
        'name' => 'CARD MAINTENANCE FEE',
        'amount' => '12.00',
        'currency' => 'PLN',
        'billing_cycle_days' => 29, // would normalize to 12.41 with naive 30/cycle
        'last_charge_at' => '2026-04-01',
        'next_expected_charge_at' => '2026-04-30',
    ]);

    $this->actingAs($user)
        ->get(route('subscriptions.index'))
        ->assertInertia(
            fn ($page) => $page->where('monthlyTotal', 12),
        );
});

it('normalizes weekly cycles to a 30-day equivalent', function () {
    $user = User::factory()->create();

    Subscription::create([
        'user_id' => $user->id,
        'name' => 'WEEKLY SERVICE',
        'amount' => '10.00',
        'currency' => 'PLN',
        'billing_cycle_days' => 7,
        'last_charge_at' => '2026-04-23',
        'next_expected_charge_at' => '2026-04-30',
    ]);

    $this->actingAs($user)
        ->get(route('subscriptions.index'))
        ->assertInertia(
            fn ($page) => $page->where(
                'monthlyTotal',
                fn (float $total): bool => abs($total - (10 * 30 / 7)) < 0.01,
            ),
        );
});

it('exposes duplicate flags and excludes them from the monthly estimate', function () {
    $user = User::factory()->create();

    $original = Subscription::create([
        'user_id' => $user->id,
        'name' => 'NETFLIX.COM',
        'amount' => '49.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-01',
        'next_expected_charge_at' => '2026-05-01',
    ]);
    Subscription::create([
        'user_id' => $user->id,
        'name' => 'NETFLIX EU',
        'amount' => '49.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-15',
        'next_expected_charge_at' => '2026-05-15',
        'is_duplicate_of_id' => $original->id,
    ]);

    $this->actingAs($user)
        ->get(route('subscriptions.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('Subscriptions/Index')
                ->has('subscriptions', 2)
                ->where('duplicateCount', 1)
                // Monthly total should count the canonical row only, not both.
                ->where('monthlyTotal', 49.99)
                ->where('subscriptions.1.is_duplicate_of_id', $original->id)
                ->where('subscriptions.1.duplicate_of_name', 'NETFLIX.COM'),
        );
});

it('dispatches a detection job when the user clicks "Run detection now"', function () {
    Bus::fake([DetectSubscriptionsJob::class]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('subscriptions.detect'))
        ->assertRedirect(route('subscriptions.index'))
        ->assertSessionHas('flash');

    Bus::assertDispatched(
        DetectSubscriptionsJob::class,
        fn (DetectSubscriptionsJob $job): bool => $job->userId === $user->id,
    );
});

it('blocks unauthenticated requests to the detect endpoint', function () {
    $this->post(route('subscriptions.detect'))->assertRedirect(route('login'));
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
