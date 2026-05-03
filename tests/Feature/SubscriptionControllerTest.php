<?php

declare(strict_types=1);

use App\Enums\DuplicateResolution;
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

it('renders the show page for the owner with charge history pulled from transactions', function () {
    $user = User::factory()->create();
    $import = Import::factory()->for($user)->create();

    $subscription = Subscription::create([
        'user_id' => $user->id,
        'name' => 'NETFLIX SUBSCRIPTION',
        'amount' => '49.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-15',
        'next_expected_charge_at' => '2026-05-15',
    ]);

    foreach ([15, 45, 75] as $i => $daysAgo) {
        Transaction::create([
            'user_id' => $user->id,
            'import_id' => $import->id,
            'posted_at' => now()->subDays($daysAgo),
            'amount' => '-49.99',
            'currency' => 'PLN',
            'description' => 'NETFLIX SUBSCRIPTION',
            'counterparty' => null,
            'balance' => null,
            'hash' => hash('sha256', "netflix-{$i}"),
        ]);
    }

    $this->actingAs($user)
        ->get(route('subscriptions.show', $subscription))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('Subscriptions/Show')
                ->where('subscription.id', $subscription->id)
                ->where('subscription.name', 'NETFLIX SUBSCRIPTION')
                ->where('stats.charge_count', 3)
                ->where('stats.total_spent', 149.97)
                ->has('charges', 3),
        );
});

it('returns 403 when one user tries to view another user\'s subscription', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $bobSubscription = Subscription::create([
        'user_id' => $bob->id,
        'name' => 'BOBs HBO',
        'amount' => '29.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-01',
        'next_expected_charge_at' => '2026-05-01',
    ]);

    $this->actingAs($alice)
        ->get(route('subscriptions.show', $bobSubscription))
        ->assertForbidden();
});

it('redirects guests away from subscription detail', function () {
    $user = User::factory()->create();
    $subscription = Subscription::create([
        'user_id' => $user->id,
        'name' => 'X',
        'amount' => '10.00',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-01',
        'next_expected_charge_at' => '2026-05-01',
    ]);

    $this->get(route('subscriptions.show', $subscription))
        ->assertRedirect(route('login'));
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

it('confirms a flagged duplicate and persists the resolution', function () {
    $user = User::factory()->create();
    $original = Subscription::create([
        'user_id' => $user->id,
        'name' => 'NETFLIX EU',
        'amount' => '49.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-01',
        'next_expected_charge_at' => '2026-05-01',
    ]);
    $duplicate = Subscription::create([
        'user_id' => $user->id,
        'name' => 'NETFLIX.COM',
        'amount' => '49.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-15',
        'next_expected_charge_at' => '2026-05-15',
        'is_duplicate_of_id' => $original->id,
    ]);

    $this->actingAs($user)
        ->post(route('subscriptions.confirm-duplicate', $duplicate))
        ->assertRedirect(route('subscriptions.show', $duplicate))
        ->assertSessionHas('flash');

    $duplicate->refresh();
    expect($duplicate->is_duplicate_of_id)->toBe($original->id);
    expect($duplicate->duplicate_resolution)
        ->toBe(DuplicateResolution::ConfirmedDuplicate);
});

it('rejects confirm-duplicate when the subscription is not flagged', function () {
    $user = User::factory()->create();
    $sub = Subscription::create([
        'user_id' => $user->id,
        'name' => 'STANDALONE',
        'amount' => '29.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-01',
        'next_expected_charge_at' => '2026-05-01',
    ]);

    $this->actingAs($user)
        ->post(route('subscriptions.confirm-duplicate', $sub))
        ->assertRedirect(route('subscriptions.show', $sub));

    $sub->refresh();
    expect($sub->duplicate_resolution)->toBeNull();
});

it('keeps a flagged subscription separate by clearing the flag and remembering the choice', function () {
    $user = User::factory()->create();
    $original = Subscription::create([
        'user_id' => $user->id,
        'name' => 'SPOTIFY',
        'amount' => '19.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-01',
        'next_expected_charge_at' => '2026-05-01',
    ]);
    $maybeDup = Subscription::create([
        'user_id' => $user->id,
        'name' => 'SPOTIFY FAMILY',
        'amount' => '21.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-10',
        'next_expected_charge_at' => '2026-05-10',
        'is_duplicate_of_id' => $original->id,
    ]);

    $this->actingAs($user)
        ->post(route('subscriptions.keep-separate', $maybeDup))
        ->assertRedirect(route('subscriptions.show', $maybeDup));

    $maybeDup->refresh();
    expect($maybeDup->is_duplicate_of_id)->toBeNull();
    expect($maybeDup->duplicate_resolution)
        ->toBe(DuplicateResolution::KeptSeparate);
});

it('returns 403 when one user tries to resolve duplicates on another user\'s subscription', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $bobSub = Subscription::create([
        'user_id' => $bob->id,
        'name' => 'BOBs HBO',
        'amount' => '29.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-01',
        'next_expected_charge_at' => '2026-05-01',
        'is_duplicate_of_id' => null,
    ]);

    $this->actingAs($alice)
        ->post(route('subscriptions.keep-separate', $bobSub))
        ->assertForbidden();
});

it('updates a subscription and recomputes the next expected charge', function () {
    $user = User::factory()->create();
    $entertainment = Category::query()->where('slug', 'entertainment')->firstOrFail();
    $sub = Subscription::create([
        'user_id' => $user->id,
        'name' => 'OLD NAME',
        'amount' => '19.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-01',
        'next_expected_charge_at' => '2026-05-01',
    ]);

    $this->actingAs($user)
        ->patch(route('subscriptions.update', $sub), [
            'name' => 'New Name',
            'amount' => '29.99',
            'currency' => 'eur',
            'billing_cycle_days' => 90,
            'last_charge_at' => '2026-04-15',
            'category_id' => $entertainment->id,
        ])
        ->assertRedirect(route('subscriptions.show', $sub))
        ->assertSessionHas('flash');

    $sub->refresh();
    expect($sub->name)->toBe('New Name');
    expect((float) $sub->amount)->toBe(29.99);
    expect($sub->currency)->toBe('EUR');
    expect($sub->billing_cycle_days)->toBe(90);
    expect($sub->last_charge_at->toDateString())->toBe('2026-04-15');
    // 2026-04-15 + 90 days = 2026-07-14
    expect($sub->next_expected_charge_at?->toDateString())->toBe('2026-07-14');
    expect($sub->category_id)->toBe($entertainment->id);
});

it('allows clearing the category by passing null', function () {
    $user = User::factory()->create();
    $entertainment = Category::query()->where('slug', 'entertainment')->firstOrFail();
    $sub = Subscription::create([
        'user_id' => $user->id,
        'category_id' => $entertainment->id,
        'name' => 'TEST',
        'amount' => '10.00',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-01',
        'next_expected_charge_at' => '2026-05-01',
    ]);

    $this->actingAs($user)
        ->patch(route('subscriptions.update', $sub), [
            'name' => 'TEST',
            'amount' => '10.00',
            'currency' => 'PLN',
            'billing_cycle_days' => 30,
            'last_charge_at' => '2026-04-01',
            'category_id' => null,
        ])
        ->assertRedirect();

    expect($sub->refresh()->category_id)->toBeNull();
});

it('rejects invalid update payloads with validation errors', function () {
    $user = User::factory()->create();
    $sub = Subscription::create([
        'user_id' => $user->id,
        'name' => 'TEST',
        'amount' => '10.00',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-01',
        'next_expected_charge_at' => '2026-05-01',
    ]);

    $this->actingAs($user)
        ->from(route('subscriptions.show', $sub))
        ->patch(route('subscriptions.update', $sub), [
            'name' => '',
            'amount' => '-5',
            'currency' => 'EURO',
            'billing_cycle_days' => 0,
            'last_charge_at' => 'tomorrow',
        ])
        ->assertRedirect(route('subscriptions.show', $sub))
        ->assertSessionHasErrors([
            'name',
            'amount',
            'currency',
            'billing_cycle_days',
            'last_charge_at',
        ]);

    // Original values untouched.
    expect($sub->refresh()->name)->toBe('TEST');
});

it('returns 403 when one user tries to update another user\'s subscription', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $bobSub = Subscription::create([
        'user_id' => $bob->id,
        'name' => 'BOBs HBO',
        'amount' => '29.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-01',
        'next_expected_charge_at' => '2026-05-01',
    ]);

    $this->actingAs($alice)
        ->patch(route('subscriptions.update', $bobSub), [
            'name' => 'pwned',
            'amount' => '0.01',
            'currency' => 'PLN',
            'billing_cycle_days' => 30,
            'last_charge_at' => '2026-04-01',
        ])
        ->assertForbidden();

    expect($bobSub->refresh()->name)->toBe('BOBs HBO');
});

it('deletes a subscription and redirects to the index', function () {
    $user = User::factory()->create();
    $sub = Subscription::create([
        'user_id' => $user->id,
        'name' => 'DELETE ME',
        'amount' => '10.00',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-01',
        'next_expected_charge_at' => '2026-05-01',
    ]);

    $this->actingAs($user)
        ->delete(route('subscriptions.destroy', $sub))
        ->assertRedirect(route('subscriptions.index'))
        ->assertSessionHas('flash');

    expect(Subscription::query()->whereKey($sub->id)->exists())->toBeFalse();
});

it('clears is_duplicate_of_id on children when their canonical is deleted', function () {
    $user = User::factory()->create();
    $canonical = Subscription::create([
        'user_id' => $user->id,
        'name' => 'NETFLIX',
        'amount' => '49.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-01',
        'next_expected_charge_at' => '2026-05-01',
    ]);
    $child = Subscription::create([
        'user_id' => $user->id,
        'name' => 'NETFLIX EU',
        'amount' => '49.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-15',
        'next_expected_charge_at' => '2026-05-15',
        'is_duplicate_of_id' => $canonical->id,
        'duplicate_resolution' => DuplicateResolution::ConfirmedDuplicate->value,
    ]);

    $this->actingAs($user)
        ->delete(route('subscriptions.destroy', $canonical))
        ->assertRedirect(route('subscriptions.index'));

    $child->refresh();
    expect($child->is_duplicate_of_id)->toBeNull();
    expect($child->duplicate_resolution)->toBeNull();
});

it('returns 403 when one user tries to delete another user\'s subscription', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $bobSub = Subscription::create([
        'user_id' => $bob->id,
        'name' => 'BOBs SUB',
        'amount' => '29.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-01',
        'next_expected_charge_at' => '2026-05-01',
    ]);

    $this->actingAs($alice)
        ->delete(route('subscriptions.destroy', $bobSub))
        ->assertForbidden();

    expect(Subscription::query()->whereKey($bobSub->id)->exists())->toBeTrue();
});
