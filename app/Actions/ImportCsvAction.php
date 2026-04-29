<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Bank;
use App\Enums\ImportStatus;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\User;
use App\Services\BankDetector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImportCsvAction
{
    public function __construct(
        private readonly BankDetector $bankDetector,
    ) {}

    /**
     * Stores the uploaded CSV under the user's private folder, creates the
     * Import row, and dispatches the parsing job. Bank may be supplied
     * explicitly (UI dropdown) — otherwise we auto-detect from headers.
     */
    public function handle(User $user, UploadedFile $file, ?Bank $bank = null): Import
    {
        $disk = Storage::disk('local');
        $directory = "imports/{$user->id}";
        $extension = strtolower($file->getClientOriginalExtension() ?: 'csv');
        if (! in_array($extension, ['csv', 'txt', 'xls', 'xlsx'], true)) {
            $extension = 'csv';
        }

        $path = $file->storeAs($directory, sprintf(
            '%s_%s.%s',
            now()->format('YmdHis'),
            bin2hex(random_bytes(4)),
            $extension,
        ), 'local');

        if ($path === false) {
            throw new RuntimeException('Failed to store uploaded CSV.');
        }

        $resolvedBank = $bank ?? $this->autoDetectBank($disk->path($path));

        $import = $user->imports()->create([
            'bank' => $resolvedBank,
            'original_filename' => $file->getClientOriginalName(),
            'status' => ImportStatus::Pending,
        ]);

        ProcessImportJob::dispatch($import->id, $path);

        return $import;
    }

    private function autoDetectBank(string $absolutePath): Bank
    {
        $parser = $this->bankDetector->detect($absolutePath);
        if ($parser === null) {
            throw new RuntimeException(
                'Could not detect bank from CSV headers. Select a bank manually.',
            );
        }

        return $parser->bank();
    }
}
