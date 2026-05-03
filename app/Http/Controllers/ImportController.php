<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ImportCsvAction;
use App\Enums\Bank;
use App\Enums\ImportStatus;
use App\Http\Requests\ImportCsvRequest;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ImportController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $imports = $user->imports()
            ->latest()
            ->limit(50)
            ->get(['id', 'bank', 'original_filename', 'stored_path', 'status', 'failed_reason', 'transactions_count', 'created_at'])
            ->map(static fn (Import $import): array => [
                'id' => $import->id,
                'bank' => $import->bank,
                'original_filename' => $import->original_filename,
                'status' => $import->status,
                'failed_reason' => $import->failed_reason,
                'transactions_count' => $import->transactions_count,
                'created_at' => $import->created_at,
                'can_retry' => $import->status === ImportStatus::Failed
                    && $import->stored_path !== null,
            ])
            ->all();

        return Inertia::render('Imports/Index', [
            'imports' => $imports,
            'banks' => collect(Bank::cases())->map(fn (Bank $b): array => [
                'value' => $b->value,
                'label' => $b->label(),
            ])->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Imports/Create', [
            'banks' => collect(Bank::cases())->map(fn (Bank $b): array => [
                'value' => $b->value,
                'label' => $b->label(),
            ])->all(),
        ]);
    }

    public function store(ImportCsvRequest $request, ImportCsvAction $action): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $bankValue = $request->input('bank');
        $bank = is_string($bankValue) && $bankValue !== '' ? Bank::from($bankValue) : null;

        try {
            $import = $action->handle($user, $request->file('file'), $bank);
        } catch (RuntimeException $e) {
            return back()
                ->with('error', $e->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('imports.index')
            ->with('success', "Import #{$import->id} queued for processing.");
    }

    public function destroy(Request $request, Import $import): RedirectResponse
    {
        $user = $request->user();
        if ($user === null || $import->user_id !== $user->id) {
            abort(403);
        }

        $import->delete();

        return back()->with('success', 'Import deleted.');
    }

    public function retry(Request $request, Import $import): RedirectResponse
    {
        $user = $request->user();
        if ($user === null || $import->user_id !== $user->id) {
            abort(403);
        }

        if ($import->status !== ImportStatus::Failed) {
            return back()->with('error', 'Only failed imports can be retried.');
        }

        if ($import->stored_path === null
            || ! Storage::disk('local')->exists($import->stored_path)) {
            return back()->with(
                'error',
                'The original CSV is no longer available — please upload it again.',
            );
        }

        $import->update([
            'status' => ImportStatus::Pending,
            'failed_reason' => null,
            'transactions_count' => 0,
        ]);

        ProcessImportJob::dispatch($import->id, $import->stored_path);

        return back()->with('success', "Import #{$import->id} re-queued for processing.");
    }
}
