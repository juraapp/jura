<?php

namespace App\Modules\Ledger\Contracts;

use App\Modules\Ledger\Enums\CategoryType;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Read-only questions other modules ask the ledger. Every method takes the user explicitly, so the
 * answers stay scoped in contexts without an authenticated user (queued jobs, console commands,
 * listeners fired from them), where the BelongsToUser global scope is off.
 */
interface LedgerQuery
{
    /**
     * Sum of expense transactions in one category, in accounts of one currency, between two dates (inclusive;
     * no end date = every expense from the start date on, future-dated ones included). With $onBudgetOnly, only
     * expenses in accounts in the budget (on budget and not archived) that were not paid from a bucket count (the spending that shrinks a
     * category's envelope; an expense paid from a bucket comes out of that bucket instead).
     */
    public function expenseTotal(int $userId, int $categoryId, string $currency, CarbonInterface $from, ?CarbonInterface $to, bool $onBudgetOnly = false): Money;

    /**
     * Several category spending totals in one query, each like expenseTotal(..., to: null, onBudgetOnly: true): the
     * expenses in one category, in accounts of one currency that are in the budget, not paid from a bucket, dated on
     * or after a day (future-dated ones included).
     *
     * @param  array<array-key, array{category_id: int, currency: string, from: CarbonInterface}>  $windows
     * @return array<array-key, Money> the same keys
     */
    public function budgetSpending(int $userId, array $windows): array;

    /**
     * Whether one of the user's accounts counts in the budget (false when it is off budget, archived or not theirs).
     */
    public function isOnBudget(int $userId, int $accountId): bool;

    /**
     * The expenses on one of the user's accounts that were paid from a bucket (trashed ones left out).
     *
     * @return Collection<int, array{id: int, bucket_id: int, amount: Money, date: CarbonInterface}>
     */
    public function bucketExpenses(int $userId, int $accountId): Collection;

    /**
     * @return list<int> ids of the user's on-budget accounts (archived ones included)
     */
    public function onBudgetAccountIds(int $userId): array;

    /**
     * @return Collection<int, \stdClass> active accounts (id, name, currency) ordered by name
     */
    public function activeAccountOptions(int $userId): Collection;

    /**
     * @param  list<string>  $columns
     * @return Collection<int, \stdClass> categories of one type ordered by name
     */
    public function categoryOptions(int $userId, CategoryType $type, array $columns = ['id', 'name']): Collection;

    /**
     * Currency code of one of the user's accounts, or null if it is not theirs.
     */
    public function accountCurrency(int $userId, int $accountId): ?string;

    /**
     * Currency of the account one of the user's transactions belongs to, or null if it is not theirs.
     */
    public function transactionCurrency(int $userId, int $transactionId): ?string;

    /**
     * One of the user's income transactions, or null if it is not theirs or not an income.
     *
     * @return array{account_id: int, amount: Money, currency: string, date: CarbonInterface}|null
     */
    public function income(int $userId, int $transactionId): ?array;

    /**
     * Real transactions that look like a planned one: same type and currency, a similar amount and a nearby date.
     * Closest amount first.
     *
     * @param  list<int>  $excludeIds
     * @return Collection<int, \stdClass> id, amount (float), date (Y-m-d), description, account
     */
    public function findSimilarTransactions(
        int $userId,
        TransactionType $type,
        string $currency,
        Money $amount,
        CarbonInterface $from,
        CarbonInterface $to,
        array $excludeIds = [],
        float $tolerance = 0.02,
    ): Collection;

    /**
     * Name of a category the user can see (their own or a system one), or null.
     */
    public function categoryName(int $userId, int $categoryId): ?string;
}
