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
use Illuminate\Support\Carbon;
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

    public function accountCurrency(int $accountId): ?string
    {
        return Account::whereKey($accountId)->value('currency');
    }

    public function findSimilarTransactions(
        TransactionType $type,
        string $currency,
        Money $amount,
        CarbonInterface $from,
        CarbonInterface $to,
        array $excludeIds = [],
        float $tolerance = 0.02,
    ): Collection {
        $low = $amount->multiply(1 - $tolerance)->toDecimalString();
        $high = $amount->multiply(1 + $tolerance)->toDecimalString();

        return Transaction::query()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->where('transactions.type', $type->value)
            ->where('accounts.currency', $currency)
            ->whereBetween('transactions.date', [$from->toDateString(), $to->toDateString()])
            ->whereBetween('transactions.amount', [$low, $high])
            ->when($excludeIds !== [], fn ($query) => $query->whereNotIn('transactions.id', $excludeIds))
            ->orderByRaw('ABS(transactions.amount - ?)', [$amount->toDecimalString()])
            ->limit(3)
            ->get(['transactions.id', 'transactions.amount', 'transactions.date', 'transactions.description', 'accounts.name as account_name'])
            ->map(fn ($row) => (object) [
                'id' => (int) $row->id,
                'amount' => $row->amount->toFloat(),
                'date' => Carbon::parse($row->date)->toDateString(),
                'description' => $row->description,
                'account' => $row->account_name,
            ]);
    }

    public function categoryName(int $categoryId): ?string
    {
        return Category::whereKey($categoryId)->value('name');
    }
}
