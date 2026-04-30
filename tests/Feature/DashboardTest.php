<?php

declare(strict_types=1);

use App\Enums\Bank;
use App\Enums\ImportStatus;
use App\Models\Category;
use App\Models\User;
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
                ->where('categoryBreakdown', []),
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
