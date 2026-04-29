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
