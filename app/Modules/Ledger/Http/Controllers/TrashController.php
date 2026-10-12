<?php

namespace App\Modules\Ledger\Http\Controllers;

use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Services\TransactionService;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Soft-deleted ledger records: everything that can be deleted can also be
 * brought back, or removed for good from here.
 */
class TrashController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Trash/Index', [
            'transactions' => Transaction::onlyTrashed()->with(['account:id,name', 'category:id,name'])
                ->latest('deleted_at')->limit(200)->get()
                ->map(fn (Transaction $transaction) => [
                    'id' => $transaction->id,
                    'type' => $transaction->type->value,
                    'description' => $transaction->description,
                    'amount' => $transaction->amount->toFloat(),
                    'currency' => $transaction->account?->currency,
                    'date' => $transaction->date->toDateString(),
                    'account' => $transaction->account?->name,
                    'category' => $transaction->category?->name,
                    'deletedAt' => $transaction->deleted_at->toIso8601String(),
                ])->values(),
            'accounts' => Account::onlyTrashed()->latest('deleted_at')->get()
                ->map(fn (Account $account) => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'currency' => $account->currency,
                    'deletedAt' => $account->deleted_at->toIso8601String(),
                ])->values(),
            'categories' => Category::onlyTrashed()->where('user_id', auth()->id())->latest('deleted_at')->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type->value,
                    'deletedAt' => $category->deleted_at->toIso8601String(),
                ])->values(),
        ]);
    }

    public function restoreTransaction(int $id, TransactionService $service): RedirectResponse
    {
        $transaction = Transaction::onlyTrashed()->findOrFail($id);
        $this->authorize('delete', $transaction);

        $service->restore($transaction);

        return back()->with('success', __('trash.restored'));
    }

    public function purgeTransaction(int $id, TransactionService $service): RedirectResponse
    {
        $transaction = Transaction::onlyTrashed()->findOrFail($id);
        $this->authorize('delete', $transaction);

        $service->forceDelete($transaction);

        return back()->with('success', __('trash.purged'));
    }

    public function restoreAccount(int $id): RedirectResponse
    {
        $account = Account::onlyTrashed()->findOrFail($id);
        $this->authorize('delete', $account);

        $account->restore();

        return back()->with('success', __('trash.restored'));
    }

    public function purgeAccount(int $id): RedirectResponse
    {
        $account = Account::onlyTrashed()->findOrFail($id);
        $this->authorize('delete', $account);

        if ($account->transactions()->withTrashed()->exists() || $account->recurringTransactions()->exists()) {
            return back()->with('error', __('trash.still_used'));
        }

        $account->forceDelete();

        return back()->with('success', __('trash.purged'));
    }

    public function restoreCategory(int $id): RedirectResponse
    {
        $category = Category::onlyTrashed()->findOrFail($id);
        $this->authorize('delete', $category);

        $category->restore();

        return back()->with('success', __('trash.restored'));
    }

    public function purgeCategory(int $id): RedirectResponse
    {
        $category = Category::onlyTrashed()->findOrFail($id);
        $this->authorize('delete', $category);

        if ($category->transactions()->withTrashed()->exists()) {
            return back()->with('error', __('trash.still_used'));
        }

        $category->forceDelete();

        return back()->with('success', __('trash.purged'));
    }
}
