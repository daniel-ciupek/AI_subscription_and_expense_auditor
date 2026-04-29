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

            $inserted = 0;
            DB::transaction(function () use ($parser, $absolutePath, $import, &$inserted): void {
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
                        $inserted++;
                    }
                }
            });

            $import->update([
                'status' => ImportStatus::Done,
                'transactions_count' => $inserted,
            ]);
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
}
