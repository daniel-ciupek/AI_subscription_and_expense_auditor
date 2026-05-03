<?php

declare(strict_types=1);

use App\Enums\Bank;
use App\Enums\ImportStatus;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Bus::fake();
});

it('requires authentication for the imports index', function () {
    $this->get(route('imports.index'))->assertRedirect(route('login'));
});

it('shows imports for the authenticated user only', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Import::factory()->state(['bank' => Bank::MBank, 'status' => ImportStatus::Done])->for($alice)->create();
    Import::factory()->state(['bank' => Bank::Ing, 'status' => ImportStatus::Done])->for($bob)->create();

    $this->actingAs($alice)
        ->get(route('imports.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('Imports/Index')
                ->has('imports', 1),
        );
});

it('stores an uploaded csv, dispatches the job, and redirects', function () {
    $user = User::factory()->create();
    $csv = file_get_contents(__DIR__.'/../../Fixtures/csv/bgz_bnp_paribas.csv');
    $file = UploadedFile::fake()->createWithContent('statement.csv', $csv);

    $this->actingAs($user)
        ->post(route('imports.store'), ['file' => $file])
        ->assertRedirect(route('imports.index'));

    expect($user->imports()->count())->toBe(1);
    expect($user->imports()->first()->bank)->toBe(Bank::BgzBnpParibas);

    Bus::assertDispatched(ProcessImportJob::class);
});

it('rejects oversized files', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('huge.csv', 11 * 1024); // 11 MB

    $this->actingAs($user)
        ->post(route('imports.store'), ['file' => $file])
        ->assertSessionHasErrors('file');
});

it('returns a friendly error when bank cannot be detected', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('garbage.csv', "foo,bar\n1,2\n");

    $this->actingAs($user)
        ->from(route('imports.create'))
        ->post(route('imports.store'), ['file' => $file])
        ->assertRedirect(route('imports.create'))
        ->assertSessionHas('error');
});

it('lets users delete their own imports', function () {
    $user = User::factory()->create();
    $import = Import::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('imports.destroy', $import))
        ->assertRedirect();

    expect(Import::withTrashed()->find($import->id)->trashed())->toBeTrue();
});

it('refuses to delete imports owned by other users', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $import = Import::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->delete(route('imports.destroy', $import))
        ->assertForbidden();
});

it('exposes can_retry=true only for failed imports with a stored path', function () {
    $user = User::factory()->create();
    Storage::disk('local')->put('imports/x/file.csv', 'data');

    $failedWithPath = Import::factory()->for($user)->state([
        'status' => ImportStatus::Failed,
        'stored_path' => 'imports/x/file.csv',
        'failed_reason' => 'parser blew up',
    ])->create();
    $failedWithoutPath = Import::factory()->for($user)->state([
        'status' => ImportStatus::Failed,
        'stored_path' => null,
    ])->create();
    $done = Import::factory()->for($user)->state([
        'status' => ImportStatus::Done,
        'stored_path' => 'imports/x/done.csv',
    ])->create();

    $this->actingAs($user)
        ->get(route('imports.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('Imports/Index')
                ->has('imports', 3)
                ->where(
                    'imports',
                    function ($imports) use ($failedWithPath, $failedWithoutPath, $done): bool {
                        $byId = collect($imports)->keyBy('id');

                        return $byId[$failedWithPath->id]['can_retry'] === true
                            && $byId[$failedWithoutPath->id]['can_retry'] === false
                            && $byId[$done->id]['can_retry'] === false;
                    },
                ),
        );
});

it('retries a failed import: resets status, dispatches the job, redirects back', function () {
    $user = User::factory()->create();
    Storage::disk('local')->put('imports/u/sample.csv', 'data');

    $import = Import::factory()->for($user)->state([
        'status' => ImportStatus::Failed,
        'stored_path' => 'imports/u/sample.csv',
        'failed_reason' => 'parser exploded',
        'transactions_count' => 0,
    ])->create();

    $this->actingAs($user)
        ->from(route('imports.index'))
        ->post(route('imports.retry', $import))
        ->assertRedirect(route('imports.index'))
        ->assertSessionHas('success');

    $import->refresh();
    expect($import->status)->toBe(ImportStatus::Pending);
    expect($import->failed_reason)->toBeNull();

    Bus::assertDispatched(
        ProcessImportJob::class,
        fn (ProcessImportJob $job): bool => $job->importId === $import->id
            && $job->storedPath === 'imports/u/sample.csv',
    );
});

it('refuses to retry an import that did not fail', function () {
    $user = User::factory()->create();
    Storage::disk('local')->put('imports/u/done.csv', 'data');

    $import = Import::factory()->for($user)->state([
        'status' => ImportStatus::Done,
        'stored_path' => 'imports/u/done.csv',
    ])->create();

    $this->actingAs($user)
        ->from(route('imports.index'))
        ->post(route('imports.retry', $import))
        ->assertRedirect(route('imports.index'))
        ->assertSessionHas('error');

    Bus::assertNotDispatched(ProcessImportJob::class);
    expect($import->refresh()->status)->toBe(ImportStatus::Done);
});

it('refuses to retry when the original CSV is gone', function () {
    $user = User::factory()->create();
    $import = Import::factory()->for($user)->state([
        'status' => ImportStatus::Failed,
        'stored_path' => 'imports/u/missing.csv',
        'failed_reason' => 'old failure',
    ])->create();

    $this->actingAs($user)
        ->from(route('imports.index'))
        ->post(route('imports.retry', $import))
        ->assertRedirect(route('imports.index'))
        ->assertSessionHas('error');

    Bus::assertNotDispatched(ProcessImportJob::class);
    // Status stays failed so the user can still see the error.
    expect($import->refresh()->status)->toBe(ImportStatus::Failed);
});

it('refuses to retry imports owned by other users', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    Storage::disk('local')->put('imports/o/file.csv', 'data');

    $import = Import::factory()->for($owner)->state([
        'status' => ImportStatus::Failed,
        'stored_path' => 'imports/o/file.csv',
    ])->create();

    $this->actingAs($intruder)
        ->post(route('imports.retry', $import))
        ->assertForbidden();

    Bus::assertNotDispatched(ProcessImportJob::class);
});

it('persists stored_path when storing an upload so retry has something to use', function () {
    $user = User::factory()->create();
    $csv = file_get_contents(__DIR__.'/../../Fixtures/csv/bgz_bnp_paribas.csv');
    $file = UploadedFile::fake()->createWithContent('statement.csv', $csv);

    $this->actingAs($user)
        ->post(route('imports.store'), ['file' => $file])
        ->assertRedirect(route('imports.index'));

    $import = $user->imports()->firstOrFail();
    expect($import->stored_path)->not->toBeNull();
    expect(Storage::disk('local')->exists((string) $import->stored_path))->toBeTrue();
});
