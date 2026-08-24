<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request, string $type = 'all'): Response
    {
        $query = Auth::user()->transactions()->with(['account', 'category'])->latest('date')->latest('id');

        if ($type === 'transfer') {
            $query->where('type', 'transfer_out');
        } elseif ($type !== 'all') {
            $query->where('type', $type);
        }

        if ($request->filled('account')) {
            $query->where('account_id', $request->query('account'));
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->query('category'));
        }

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->query('to'));
        }

        if ($request->filled('q')) {
            $query->where('description', 'like', '%'.$request->query('q').'%');
        }

        $transactions = $query->paginate(15)->withQueryString();

        $transferDestinations = collect();
        if ($type === 'transfer') {
            $transferDestinations = Transaction::whereIn('transfer_group_id', $transactions->pluck('transfer_group_id'))
                ->where('type', 'transfer_in')
                ->with('account')
                ->get()
                ->keyBy('transfer_group_id');
        }

        $transactions->through(fn (Transaction $transaction) => [
            'id' => $transaction->id,
            'type' => $transaction->type->value,
            'date' => $transaction->date->toDateString(),
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
            'transferDestinationAccount' => $transferDestinations->get($transaction->transfer_group_id)?->account->name,
        ]);

        return Inertia::render('Transactions/Index', [
            'typeFilter' => $type,
            'transactions' => $transactions,
            'accounts' => Account::orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name', 'type']),
            'filters' => $request->only(['account', 'category', 'from', 'to', 'q']),
        ]);
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
