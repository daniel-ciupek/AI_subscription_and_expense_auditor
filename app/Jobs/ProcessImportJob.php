<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\ParsedTransaction;
use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\Transaction;
use App\Services\BankDetector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $importId,
        public readonly string $storedPath,
    ) {}

    public function handle(BankDetector $detector): void
    {
        $import = Import::findOrFail($this->importId);

        $import->update(['status' => ImportStatus::Processing]);

        try {
            $parser = $detector->parserFor($import->bank);
            $absolutePath = Storage::disk('local')->path($this->storedPath);

            /** @var array<int, int> $insertedIds */
            $insertedIds = [];
            DB::transaction(function () use ($parser, $absolutePath, $import, &$insertedIds): void {
                /** @var ParsedTransaction $parsed */
                foreach ($parser->parse($absolutePath) as $parsed) {
                    $hash = Transaction::buildHash(
                        userId: $import->user_id,
                        postedAt: $parsed->postedAt->toDateString(),
                        amount: $parsed->amount,
                        description: $parsed->description,
                        balance: $parsed->balance,
                    );

                    $transaction = Transaction::firstOrCreate(
                        [
                            'user_id' => $import->user_id,
                            'hash' => $hash,
                        ],
                        [
                            'import_id' => $import->id,
                            'posted_at' => $parsed->postedAt,
                            'amount' => $parsed->amount,
                            'currency' => $parsed->currency,
                            'description' => $parsed->description,
                            'counterparty' => $parsed->counterparty,
                            'balance' => $parsed->balance,
                        ],
                    );

                    if ($transaction->wasRecentlyCreated) {
                        $insertedIds[] = $transaction->id;
                    }
                }
            });

            $import->update([
                'status' => ImportStatus::Done,
                'transactions_count' => count($insertedIds),
            ]);

            $this->dispatchPostImportPipeline($import->user_id, $insertedIds);
        } catch (Throwable $e) {
            Log::error('Import processing failed', [
                'import_id' => $import->id,
                'error' => $e->getMessage(),
            ]);

            $import->update([
                'status' => ImportStatus::Failed,
                'failed_reason' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<int, int>  $insertedIds
     */
    private function dispatchPostImportPipeline(int $userId, array $insertedIds): void
    {
        if ($insertedIds === []) {
            return;
        }

        $jobs = array_map(
            static fn (array $chunk): CategorizeTransactionsJob => new CategorizeTransactionsJob($chunk),
            array_chunk($insertedIds, CategorizeTransactionsJob::MAX_BATCH),
        );

        Bus::batch($jobs)
            ->name("import:{$this->importId}:categorize")
            ->allowFailures()
            ->then(static function () use ($userId): void {
                DetectSubscriptionsJob::dispatch($userId);
            })
            ->dispatch();
    }
}
