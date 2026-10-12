<?php

namespace App\Modules\Ledger\Services;

use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Enums\CategoryType;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EloquentLedgerQuery implements LedgerQuery
{
    public function expenseTotal(int $categoryId, string $currency, CarbonInterface $from, CarbonInterface $to): Money
    {
        return Money::of(
            Transaction::query()
                ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
                ->where('transactions.category_id', $categoryId)
                ->where('transactions.type', TransactionType::Expense->value)
                ->where('accounts.currency', $currency)
                ->whereBetween('transactions.date', [$from, $to])
                ->sum('transactions.amount')
        );
    }

    public function activeAccountOptions(): Collection
    {
        return Account::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function categoryOptions(CategoryType $type, array $columns = ['id', 'name']): Collection
    {
        return Category::where('type', $type)->orderBy('name')->get($columns);
    }
}
