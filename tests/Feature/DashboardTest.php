<?php

declare(strict_types=1);

use App\Enums\Bank;
use App\Enums\ImportStatus;
use App\Models\Category;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the dashboard with zero stats and empty state for a fresh user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('Dashboard')
                ->where('stats.transactions', 0)
                ->where('stats.subscriptions', 0)
                ->where('recentTransactions', [])
                ->where('categoryBreakdown', [])
                // 90-day daily series, all zeros for a fresh user.
                ->has('spendingOverTime', 90),
        );
});

it('builds a 90-day daily spending series with zero-filled gaps', function () {
    $user = User::factory()->create();
    $import = $user->imports()->create([
        'bank' => Bank::BgzBnpParibas,
        'original_filename' => 'sample.xlsx',
        'status' => ImportStatus::Done,
    ]);

    $today = CarbonImmutable::now()->startOfDay();

    // Two expenses on the same day → should be summed in that bucket.
    foreach (['-50.00', '-30.00'] as $i => $amount) {
        $user->transactions()->create([
            'import_id' => $import->id,
            'posted_at' => $today->subDays(5)->toDateString(),
            'amount' => $amount,
            'currency' => 'PLN',
            'description' => "spend {$i}",
            'counterparty' => null,
            'balance' => null,
            'hash' => hash('sha256', "spend-{$i}"),
        ]);
    }

    // Income — must not show up in the spending chart.
    $user->transactions()->create([
        'import_id' => $import->id,
        'posted_at' => $today->subDays(5)->toDateString(),
        'amount' => '5000.00',
        'currency' => 'PLN',
        'description' => 'salary',
        'counterparty' => null,
        'balance' => null,
        'hash' => hash('sha256', 'income-1'),
    ]);

    // Expense outside the 90-day window — must not be included.
    $user->transactions()->create([
        'import_id' => $import->id,
        'posted_at' => $today->subDays(120)->toDateString(),
        'amount' => '-1000.00',
        'currency' => 'PLN',
        'description' => 'old',
        'counterparty' => null,
        'balance' => null,
        'hash' => hash('sha256', 'old-1'),
    ]);

    // Series is indexed [today-89 .. today]; 5 days ago lands on index 84.
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn ($page) => $page->component('Dashboard')
                ->has('spendingOverTime', 90)
                ->where('spendingOverTime.0.date', $today->subDays(89)->toDateString())
                ->where('spendingOverTime.89.date', $today->toDateString())
                ->where('spendingOverTime.84.date', $today->subDays(5)->toDateString())
                // Two same-day expenses sum, salary excluded, 120-days-ago row excluded.
                ->where('spendingOverTime.84.total', 80)
                ->where('spendingOverTime.83.total', 0),
        );
});

it('reports real transaction counts and the most recent rows', function () {
    $user = User::factory()->create();
    $import = $user->imports()->create([
        'bank' => Bank::BgzBnpParibas,
        'original_filename' => 'sample.xlsx',
        'status' => ImportStatus::Done,
    ]);

    foreach (range(1, 12) as $i) {
        $user->transactions()->create([
            'import_id' => $import->id,
            'posted_at' => now()->subDays($i),
            'amount' => '-'.($i * 10).'.00',
            'currency' => 'PLN',
            'description' => "Transaction {$i}",
            'counterparty' => null,
            'balance' => null,
            'hash' => hash('sha256', "tx-{$i}"),
        ]);
    }

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('Dashboard')
                ->where('stats.transactions', 12)
                ->has('recentTransactions', 10),
        );
});

it('aggregates expenses by category for the breakdown chart', function () {
    $this->seed(CategorySeeder::class);

    $user = User::factory()->create();
    $import = $user->imports()->create([
        'bank' => Bank::BgzBnpParibas,
        'original_filename' => 'sample.xlsx',
        'status' => ImportStatus::Done,
    ]);

    $food = Category::query()->where('slug', 'food')->firstOrFail();
    $subs = Category::query()->where('slug', 'subscriptions')->firstOrFail();
    $salary = Category::query()->where('slug', 'salary')->firstOrFail();

    $rows = [
        ['amount' => '-50.00', 'category_id' => $food->id, 'desc' => 'Biedronka 1'],
        ['amount' => '-30.00', 'category_id' => $food->id, 'desc' => 'Biedronka 2'],
        ['amount' => '-49.99', 'category_id' => $subs->id, 'desc' => 'Netflix'],
        // Income (positive amount) — must NOT appear in expense breakdown.
        ['amount' => '5000.00', 'category_id' => $salary->id, 'desc' => 'Wynagrodzenie'],
    ];

    foreach ($rows as $i => $row) {
        $user->transactions()->create([
            'import_id' => $import->id,
            'category_id' => $row['category_id'],
            'posted_at' => now()->subDays($i),
            'amount' => $row['amount'],
            'currency' => 'PLN',
            'description' => $row['desc'],
            'counterparty' => null,
            'balance' => null,
            'hash' => hash('sha256', "br-{$i}"),
        ]);
    }

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('Dashboard')
                ->has('categoryBreakdown', 2)
                ->where('categoryBreakdown.0.slug', 'food')
                ->where('categoryBreakdown.0.total', 80)
                ->where('categoryBreakdown.1.slug', 'subscriptions')
                ->where('categoryBreakdown.1.total', 49.99),
        );
});

it('exposes top subscriptions sorted by monthly cost, hiding duplicates', function () {
    $this->seed(CategorySeeder::class);

    $user = User::factory()->create();
    $subs = Category::query()->where('slug', 'subscriptions')->firstOrFail();

    $netflix = Subscription::create([
        'user_id' => $user->id,
        'category_id' => $subs->id,
        'name' => 'NETFLIX',
        'amount' => '49.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-01',
        'next_expected_charge_at' => '2026-05-01',
    ]);
    Subscription::create([
        'user_id' => $user->id,
        'category_id' => $subs->id,
        'name' => 'SPOTIFY',
        'amount' => '23.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-15',
        'next_expected_charge_at' => '2026-05-15',
    ]);
    // Duplicate of Netflix — must NOT appear in the top list.
    Subscription::create([
        'user_id' => $user->id,
        'category_id' => $subs->id,
        'name' => 'NETFLIX EU',
        'amount' => '49.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => '2026-04-20',
        'next_expected_charge_at' => '2026-05-20',
        'is_duplicate_of_id' => $netflix->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn ($page) => $page->component('Dashboard')
                ->has('topSubscriptions', 2)
                ->where('topSubscriptions.0.name', 'NETFLIX')
                ->where('topSubscriptions.0.monthly_cost', 49.99)
                ->where('topSubscriptions.1.name', 'SPOTIFY')
                ->where('topSubscriptions.1.monthly_cost', 23.99),
        );
});

it('caps top subscriptions list at 5 entries', function () {
    $user = User::factory()->create();

    foreach (range(1, 8) as $i) {
        Subscription::create([
            'user_id' => $user->id,
            'name' => "SERVICE {$i}",
            'amount' => (string) (10 + $i),
            'currency' => 'PLN',
            'billing_cycle_days' => 30,
            'last_charge_at' => '2026-04-01',
            'next_expected_charge_at' => '2026-05-01',
        ]);
    }

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn ($page) => $page->component('Dashboard')
                ->has('topSubscriptions', 5)
                // Sorted desc → highest amount first.
                ->where('topSubscriptions.0.name', 'SERVICE 8'),
        );
});

it('labels uncategorized expenses in the breakdown', function () {
    $user = User::factory()->create();
    $import = $user->imports()->create([
        'bank' => Bank::BgzBnpParibas,
        'original_filename' => 'sample.xlsx',
        'status' => ImportStatus::Done,
    ]);

    $user->transactions()->create([
        'import_id' => $import->id,
        'category_id' => null,
        'posted_at' => now(),
        'amount' => '-12.00',
        'currency' => 'PLN',
        'description' => 'Mystery merchant',
        'counterparty' => null,
        'balance' => null,
        'hash' => hash('sha256', 'unc-1'),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn ($page) => $page->component('Dashboard')
                ->has('categoryBreakdown', 1)
                ->where('categoryBreakdown.0.slug', 'uncategorized')
                ->where('categoryBreakdown.0.total', 12),
        );
});
