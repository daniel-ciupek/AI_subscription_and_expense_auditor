<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Import;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Permanently removes imports that the user soft-deleted more than --days
 * ago (default 30). Force-delete cascades through transactions.import_id
 * so the underlying transaction history goes with them. The original
 * uploaded CSV is removed from local storage too — keeping it around
 * after the row is gone would be a privacy regression.
 */
class PruneDeletedImportsCommand extends Command
{
    protected $signature = 'imports:prune-deleted
        {--days=30 : Soft-delete TTL in days. Imports trashed earlier than this are force-deleted.}';

    protected $description = 'Permanently delete imports past the soft-delete TTL';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days < 0) {
            $this->error('--days must be zero or positive.');

            return self::INVALID;
        }

        $cutoff = CarbonImmutable::now()->subDays($days);

        /** @var iterable<Import> $expired */
        $expired = Import::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->cursor();

        $pruned = 0;
        $fileErrors = 0;
        foreach ($expired as $import) {
            if ($import->stored_path !== null) {
                try {
                    Storage::disk('local')->delete($import->stored_path);
                } catch (Throwable $e) {
                    // Don't let a missing file block the row deletion —
                    // the goal is to drop user data on time, not to babysit
                    // the filesystem.
                    $fileErrors++;
                    Log::warning('Failed to delete stored CSV during prune', [
                        'import_id' => $import->id,
                        'stored_path' => $import->stored_path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $import->forceDelete();
            $pruned++;
        }

        $this->info("Pruned {$pruned} expired imports (>{$days}d). File-delete errors: {$fileErrors}.");

        return self::SUCCESS;
    }
}
