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
    public function expenseTotal(int $userId, int $categoryId, string $currency, CarbonInterface $from, ?CarbonInterface $to, bool $onBudgetOnly = false): Money
    {
        return Money::of(
            Transaction::forUser($userId)
                ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
                ->where('transactions.category_id', $categoryId)
                ->where('transactions.type', TransactionType::Expense->value)
                ->where('accounts.currency', $currency)
                ->where('transactions.date', '>=', $from->toDateString())
                ->when($to !== null, fn ($query) => $query->where('transactions.date', '<=', $to->toDateString()))
                ->when($onBudgetOnly, fn ($query) => $query->where('accounts.on_budget', true)->where('accounts.is_active', true)
                    ->whereNull('accounts.deleted_at')->whereNull('transactions.bucket_id'))
                ->sum('transactions.amount')
        );
    }

    public function budgetSpending(int $userId, array $windows): array
    {
        if ($windows === []) {
            return [];
        }

        $keys = array_keys($windows);
        $query = Transaction::forUser($userId)
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->where('transactions.type', TransactionType::Expense->value)
            ->whereIn('transactions.category_id', array_unique(array_column($windows, 'category_id')))
            ->where('accounts.on_budget', true)
            ->where('accounts.is_active', true)
            ->whereNull('accounts.deleted_at')
            ->whereNull('transactions.bucket_id');

        foreach (array_values($windows) as $index => $window) {
            $query->selectRaw(
                "COALESCE(SUM(CASE WHEN transactions.category_id = ? AND accounts.currency = ? AND transactions.date >= ? THEN transactions.amount ELSE 0 END), 0) AS w{$index}",
                [$window['category_id'], strtoupper($window['currency']), $window['from']->toDateString()],
            );
        }

        $row = (array) $query->toBase()->first();

        $totals = [];
        foreach ($keys as $index => $key) {
            $totals[$key] = Money::of($row["w{$index}"] ?? 0);
        }

        return $totals;
    }

    public function isOnBudget(int $userId, int $accountId): bool
    {
        return Account::forUser($userId)->whereKey($accountId)->where('on_budget', true)->where('is_active', true)->exists();
    }

    public function bucketExpenses(int $userId, int $accountId): Collection
    {
        return Transaction::forUser($userId)
            ->where('account_id', $accountId)
            ->where('type', TransactionType::Expense->value)
            ->whereNotNull('bucket_id')
            ->orderBy('id')
            ->get(['id', 'bucket_id', 'amount', 'date'])
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'bucket_id' => $transaction->bucket_id,
                'amount' => $transaction->amount,
                'date' => $transaction->date,
            ]);
    }

    public function onBudgetAccountIds(int $userId): array
    {
        return Account::forUser($userId)->where('on_budget', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function activeAccountOptions(int $userId): Collection
    {
        return Account::forUser($userId)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'currency']);
    }

    public function categoryOptions(int $userId, CategoryType $type, array $columns = ['id', 'name']): Collection
    {
        return Category::visibleTo($userId)->where('type', $type)->orderBy('name')->get($columns);
    }

    public function accountCurrency(int $userId, int $accountId): ?string
    {
        return Account::forUser($userId)->whereKey($accountId)->value('currency');
    }

    public function transactionCurrency(int $userId, int $transactionId): ?string
    {
        return Transaction::forUser($userId)
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->where('transactions.id', $transactionId)
            ->value('accounts.currency');
    }

    public function income(int $userId, int $transactionId): ?array
    {
        $transaction = Transaction::forUser($userId)
            ->with('account:id,currency')
            ->where('type', TransactionType::Income->value)
            ->find($transactionId);

        if ($transaction === null || $transaction->account === null) {
            return null;
        }

        return [
            'account_id' => $transaction->account_id,
            'amount' => $transaction->amount,
            'currency' => $transaction->account->currency,
            'date' => $transaction->date,
        ];
    }

    public function findSimilarTransactions(
        int $userId,
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

        return Transaction::forUser($userId)
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

    public function categoryName(int $userId, int $categoryId): ?string
    {
        return Category::visibleTo($userId)->whereKey($categoryId)->value('name');
    }
}
