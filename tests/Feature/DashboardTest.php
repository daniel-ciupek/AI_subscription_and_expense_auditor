<?php

declare(strict_types=1);

use App\Enums\Bank;
use App\Enums\ImportStatus;
use App\Models\User;
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
                ->where('recentTransactions', []),
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
