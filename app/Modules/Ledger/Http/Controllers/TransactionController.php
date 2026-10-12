<?php

namespace App\Modules\Ledger\Http\Controllers;

use App\Modules\Ledger\Enums\CategoryType;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Ledger\Http\Requests\Transactions\BulkTransactionsRequest;
use App\Modules\Ledger\Http\Requests\Transactions\BulkUpdateTransactionsRequest;
use App\Modules\Ledger\Http\Requests\Transactions\StoreTransactionRequest;
use App\Modules\Ledger\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Services\TransactionService;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    private const BULK_LIMIT = 2000;

    public function index(Request $request, string $type = 'all'): Response
    {
        $query = $this->filteredQuery($type, $request->only(['account', 'category', 'from', 'to', 'q']));

        $transactions = $query->paginate(15)->withQueryString();

        $counterparts = Transaction::whereIn('transfer_group_id', $transactions->pluck('transfer_group_id')->filter())
            ->with('account:id,name')
            ->get()
            ->groupBy('transfer_group_id');

        $transactions->through(fn (Transaction $transaction) => [
            'id' => $transaction->id,
            'type' => $transaction->type->value,
            'date' => $transaction->date->toDateString(),
            'time' => $transaction->time,
            'description' => $transaction->description,
            'notes' => $transaction->notes,
            'amount' => $transaction->amount->toFloat(),
            'paymentMethod' => $transaction->payment_method?->value,
            'account' => [
                'id' => $transaction->account->id,
                'name' => $transaction->account->name,
                'currency' => $transaction->account->currency,
            ],
            'category' => $transaction->category ? [
                'id' => $transaction->category->id,
                'name' => $transaction->category->name,
                'icon' => $transaction->category->icon,
                'color' => $transaction->category->color,
            ] : null,
            'counterpartAccount' => $counterparts->get($transaction->transfer_group_id)
                ?->firstWhere('id', '!=', $transaction->id)?->account?->name,
        ]);

        return Inertia::render('Transactions/Index', [
            'typeFilter' => $type,
            'transactions' => $transactions,
            'accounts' => Account::orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name', 'type']),
            'filters' => $request->only(['account', 'category', 'from', 'to', 'q']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters  account, category, from, to, q
     * @return Builder<Transaction>
     */
    private function filteredQuery(string $type, array $filters): Builder
    {
        $query = Transaction::with(['account', 'category'])->latest('date')->orderByRaw('time is null')->latest('time')->latest('id');

        if ($type === 'transfer') {
            $query->where('type', 'transfer_out');
        } elseif ($type !== 'all') {
            $query->where('type', $type);
        }

        if (! empty($filters['account'])) {
            $query->where('account_id', $filters['account']);
        }

        if (! empty($filters['category'])) {
            $query->where('category_id', $filters['category']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('date', '<=', $filters['to']);
        }

        if (! empty($filters['q'])) {
            $query->where('description', 'like', '%'.$filters['q'].'%');
        }

        return $query;
    }

    /**
     * The transactions a bulk request points at: explicit ids, or every row the list filters match.
     *
     * @return Collection<int, Transaction>
     */
    private function bulkSelection(BulkTransactionsRequest $request): Collection
    {
        if ($request->boolean('all_matching')) {
            return $this->filteredQuery($request->input('type', 'all'), (array) $request->input('filters', []))
                ->limit(self::BULK_LIMIT)
                ->get();
        }

        return Auth::user()->transactions()->with('account')->whereIn('id', $request->input('ids', []))->get();
    }

    public function bulkDestroy(BulkTransactionsRequest $request, TransactionService $service): RedirectResponse
    {
        $deleted = [];

        foreach ($this->bulkSelection($request) as $transaction) {
            if ($transaction->fresh() === null) {
                continue;
            }

            $this->authorize('delete', $transaction);

            $legs = $transaction->type->isTransfer() && $transaction->transfer_group_id
                ? Transaction::where('transfer_group_id', $transaction->transfer_group_id)->pluck('id')->all()
                : [$transaction->id];

            $service->delete($transaction);
            array_push($deleted, ...$legs);
        }

        $deleted = array_values(array_unique($deleted));

        return back()->with([
            'success' => __('transactions.bulk_deleted', ['count' => count($deleted)]),
            'undo' => $deleted === [] ? null : ['url' => route('transactions.bulk-restore'), 'ids' => $deleted],
        ]);
    }

    public function bulkRestore(Request $request, TransactionService $service): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:'.BulkTransactionsRequest::MAX_IDS], 'ids.*' => ['integer']])['ids'];
        $restored = 0;

        foreach (Transaction::onlyTrashed()->whereIn('id', $ids)->get() as $transaction) {
            $this->authorize('delete', $transaction);
            $service->restore($transaction);
            $restored++;
        }

        return back()->with('success', __('transactions.bulk_restored', ['count' => $restored]));
    }

    public function bulkUpdate(BulkUpdateTransactionsRequest $request, TransactionService $service): RedirectResponse
    {
        $category = $request->filled('category_id') ? Category::find($request->integer('category_id')) : null;
        $accountId = $request->filled('account_id') ? $request->integer('account_id') : null;
        $changed = 0;

        foreach ($this->bulkSelection($request) as $transaction) {
            $this->authorize('update', $transaction);

            if ($service->reassign($transaction, $accountId, $category)) {
                $changed++;
            }
        }

        return back()->with('success', __('transactions.bulk_updated', ['count' => $changed]));
    }

    public function formOptions(): JsonResponse
    {
        return response()->json([
            'accounts' => Account::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'expenseCategories' => Category::where('type', CategoryType::Expense)->orderBy('name')->get(['id', 'name']),
            'incomeCategories' => Category::where('type', CategoryType::Income)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function editData(Transaction $transaction): JsonResponse
    {
        $this->authorize('update', $transaction);

        if ($transaction->type->isTransfer()) {
            $sibling = Transaction::where('transfer_group_id', $transaction->transfer_group_id)
                ->whereKeyNot($transaction->id)
                ->first();

            $out = $transaction->type === TransactionType::TransferOut ? $transaction : $sibling;
            $in = $transaction->type === TransactionType::TransferIn ? $transaction : $sibling;

            return response()->json([
                'id' => $transaction->id,
                'type' => 'transfer',
                'from_account_id' => $out?->account_id,
                'to_account_id' => $in?->account_id,
                'amount' => $transaction->amount->toFloat(),
                'date' => $transaction->date->toDateString(),
                'time' => $transaction->time,
                'description' => $transaction->description,
                'notes' => $transaction->notes,
            ]);
        }

        return response()->json([
            'id' => $transaction->id,
            'type' => $transaction->type->value,
            'account_id' => $transaction->account_id,
            'category_id' => $transaction->category_id,
            'amount' => $transaction->amount->toFloat(),
            'date' => $transaction->date->toDateString(),
            'time' => $transaction->time,
            'description' => $transaction->description,
            'notes' => $transaction->notes,
            'payment_method' => $transaction->payment_method?->value,
        ]);
    }

    public function store(StoreTransactionRequest $request, TransactionService $service): RedirectResponse
    {
        $data = $request->validated();

        if ($data['type'] === 'transfer') {
            $service->createTransfer(Auth::user(), $data);
        } else {
            $type = TransactionType::from($data['type']);

            $type === TransactionType::Income
                ? $service->createIncome(Auth::user(), $data)
                : $service->createExpense(Auth::user(), $data);
        }

        return back()->with('success', __('transactions.saved'));
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction, TransactionService $service): RedirectResponse
    {
        $data = $request->validated();

        $transaction->type->isTransfer()
            ? $service->updateTransfer($transaction, $data)
            : $service->updateSimple($transaction, $data);

        return back()->with('success', __('transactions.saved'));
    }

    public function destroy(Transaction $transaction, TransactionService $service): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $service->delete($transaction);

        return back()->with('success', __('transactions.deleted'));
    }
}
