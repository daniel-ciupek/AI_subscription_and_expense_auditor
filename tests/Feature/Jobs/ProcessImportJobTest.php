<?php

declare(strict_types=1);

use App\Enums\Bank;
use App\Enums\ImportStatus;
use App\Jobs\CategorizeTransactionsJob;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BankDetector;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Bus::fake();

    $this->user = User::factory()->create();
});

function makeImportWithFixture(User $user, Bank $bank, string $fixture): array
{
    $relativePath = "imports/{$user->id}/sample.csv";
    Storage::disk('local')->put($relativePath, file_get_contents(__DIR__.'/../../Fixtures/csv/'.$fixture));

    $import = $user->imports()->create([
        'bank' => $bank,
        'original_filename' => $fixture,
        'status' => ImportStatus::Pending,
    ]);

    return [$import, $relativePath];
}

it('imports BGŻ transactions and marks the import done', function () {
    [$import, $path] = makeImportWithFixture($this->user, Bank::BgzBnpParibas, 'bgz_bnp_paribas.csv');

    (new ProcessImportJob($import->id, $path))->handle(app(BankDetector::class));

    $import->refresh();
    expect($import->status)->toBe(ImportStatus::Done);
    expect($import->transactions_count)->toBe(3);
    expect($this->user->transactions()->count())->toBe(3);
});

it('batches a categorize job per chunk for newly inserted transactions', function () {
    [$import, $path] = makeImportWithFixture($this->user, Bank::BgzBnpParibas, 'bgz_bnp_paribas.csv');

    (new ProcessImportJob($import->id, $path))->handle(app(BankDetector::class));

    Bus::assertBatched(function (PendingBatch $batch): bool {
        if ($batch->jobs->count() !== 1) {
            return false;
        }
        $job = $batch->jobs->first();

        return $job instanceof CategorizeTransactionsJob
            && count($job->transactionIds) === 3;
    });
    Bus::assertBatchCount(1);
});

it('is idempotent — re-running the same import inserts zero new rows', function () {
    [$import, $path] = makeImportWithFixture($this->user, Bank::BgzBnpParibas, 'bgz_bnp_paribas.csv');

    (new ProcessImportJob($import->id, $path))->handle(app(BankDetector::class));
    expect(Transaction::count())->toBe(3);

    [$import2, $path2] = makeImportWithFixture($this->user, Bank::BgzBnpParibas, 'bgz_bnp_paribas.csv');
    (new ProcessImportJob($import2->id, $path2))->handle(app(BankDetector::class));

    expect(Transaction::count())->toBe(3); // still only 3 rows total
    expect($import2->fresh()->transactions_count)->toBe(0);
    expect($import2->fresh()->status)->toBe(ImportStatus::Done);

    // First run dispatched 1 categorize batch; the idempotent re-run inserted
    // nothing, so it must NOT have dispatched another batch.
    Bus::assertBatchCount(1);
});

it('marks import failed and records the reason on parser errors', function () {
    // mBank parser expects a yyyy-mm-dd date in column 0; "not-a-date" will throw.
    Storage::disk('local')->put('imports/broken.csv', "header1;header2;header3;header4;header5;header6;header7;header8\nnot-a-date;x;x;x;x;x;x;x\n");

    $import = $this->user->imports()->create([
        'bank' => Bank::MBank,
        'original_filename' => 'broken.csv',
        'status' => ImportStatus::Pending,
    ]);

    try {
        (new ProcessImportJob($import->id, 'imports/broken.csv'))->handle(app(BankDetector::class));
    } catch (Throwable) {
        // job rethrows after marking the import; that is expected.
    }

    $import->refresh();
    expect($import->status)->toBe(ImportStatus::Failed);
    expect($import->failed_reason)->not->toBeNull();
});
