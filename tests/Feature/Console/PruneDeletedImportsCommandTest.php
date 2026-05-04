<?php

declare(strict_types=1);

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('force-deletes imports trashed more than 30 days ago', function () {
    $user = User::factory()->create();

    $expired = Import::factory()->for($user)->create([
        'status' => ImportStatus::Done,
    ]);
    $expired->delete();
    $expired->forceFill(['deleted_at' => CarbonImmutable::now()->subDays(31)])->save();

    $this->artisan('imports:prune-deleted')->assertSuccessful();

    expect(Import::withTrashed()->find($expired->id))->toBeNull();
});

it('keeps imports still inside the soft-delete TTL', function () {
    $user = User::factory()->create();

    $recent = Import::factory()->for($user)->create();
    $recent->delete();
    $recent->forceFill(['deleted_at' => CarbonImmutable::now()->subDays(7)])->save();

    $this->artisan('imports:prune-deleted')->assertSuccessful();

    expect(Import::withTrashed()->find($recent->id))->not->toBeNull();
});

it('leaves active (non-trashed) imports alone', function () {
    $user = User::factory()->create();

    $active = Import::factory()->for($user)->create();

    $this->artisan('imports:prune-deleted')->assertSuccessful();

    expect(Import::find($active->id))->not->toBeNull();
});

it('removes the underlying CSV from storage when pruning', function () {
    $user = User::factory()->create();
    Storage::disk('local')->put('imports/u/old.csv', 'data');

    $import = Import::factory()->for($user)->create([
        'stored_path' => 'imports/u/old.csv',
    ]);
    $import->delete();
    $import->forceFill(['deleted_at' => CarbonImmutable::now()->subDays(45)])->save();

    expect(Storage::disk('local')->exists('imports/u/old.csv'))->toBeTrue();

    $this->artisan('imports:prune-deleted')->assertSuccessful();

    expect(Storage::disk('local')->exists('imports/u/old.csv'))->toBeFalse();
});

it('cascades the force-delete through to associated transactions', function () {
    $user = User::factory()->create();

    $import = Import::factory()->for($user)->create();
    Transaction::create([
        'user_id' => $user->id,
        'import_id' => $import->id,
        'posted_at' => '2026-01-01',
        'amount' => '-49.99',
        'currency' => 'PLN',
        'description' => 'NETFLIX',
        'counterparty' => null,
        'balance' => null,
        'hash' => hash('sha256', 'tx-prune-test'),
    ]);

    $import->delete();
    $import->forceFill(['deleted_at' => CarbonImmutable::now()->subDays(60)])->save();

    expect(Transaction::where('import_id', $import->id)->count())->toBe(1);

    $this->artisan('imports:prune-deleted')->assertSuccessful();

    expect(Transaction::where('import_id', $import->id)->count())->toBe(0);
});

it('respects the --days override', function () {
    $user = User::factory()->create();

    $import = Import::factory()->for($user)->create();
    $import->delete();
    $import->forceFill(['deleted_at' => CarbonImmutable::now()->subDays(10)])->save();

    // Default TTL would keep this; bumping --days down should prune it.
    $this->artisan('imports:prune-deleted', ['--days' => 7])->assertSuccessful();

    expect(Import::withTrashed()->find($import->id))->toBeNull();
});

it('refuses negative --days', function () {
    $this->artisan('imports:prune-deleted', ['--days' => -1])->assertFailed();
});

it('does not blow up when the stored CSV is already gone', function () {
    $user = User::factory()->create();

    $import = Import::factory()->for($user)->create([
        'stored_path' => 'imports/u/missing.csv',
    ]);
    $import->delete();
    $import->forceFill(['deleted_at' => CarbonImmutable::now()->subDays(60)])->save();

    $this->artisan('imports:prune-deleted')->assertSuccessful();

    expect(Import::withTrashed()->find($import->id))->toBeNull();
});
