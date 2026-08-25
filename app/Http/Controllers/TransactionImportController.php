<?php

namespace App\Http\Controllers;

use App\Exceptions\TransactionImportException;
use App\Models\Transaction;
use App\Services\TransactionImportService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TransactionImportController extends Controller
{
    public function create(): Response
    {
        $this->authorize('create', Transaction::class);

        return Inertia::render('Transactions/Import');
    }

    public function parse(Request $request, TransactionImportService $importer): JsonResponse
    {
        $this->authorize('create', Transaction::class);

        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        try {
            $rawRows = $importer->parse($request->file('file')->getRealPath());
        } catch (TransactionImportException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        if (empty($rawRows)) {
            return response()->json(['error' => __('messages.import.empty_file')], 422);
        }

        $rows = $importer->validateRows(Auth::user(), $rawRows);

        return response()->json([
            'rows' => collect($rows)->map(fn (array $row) => [
                'line' => $row['line'],
                'status' => $row['status'],
                'errors' => $row['errors'],
                'raw' => $row['raw'],
            ])->all(),
        ]);
    }

    public function store(Request $request, TransactionImportService $importer, TransactionService $service): JsonResponse
    {
        $this->authorize('create', Transaction::class);

        $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.line' => ['required', 'integer'],
            'include_duplicates' => ['boolean'],
        ]);

        $revalidated = $importer->validateRows(Auth::user(), $request->input('rows'));
        $includeDuplicates = $request->boolean('include_duplicates');

        $rowsToImport = array_filter(
            $revalidated,
            fn (array $row) => $row['status'] === 'valid' || ($row['status'] === 'duplicate' && $includeDuplicates)
        );

        $result = $importer->import(Auth::user(), $rowsToImport, $service);

        return response()->json([
            'imported' => $result['imported'],
            'skipped' => count(array_filter($revalidated, fn (array $row) => $row['status'] === 'error')),
            'duplicateSkipped' => $includeDuplicates ? 0 : count(array_filter($revalidated, fn (array $row) => $row['status'] === 'duplicate')),
        ]);
    }
}
