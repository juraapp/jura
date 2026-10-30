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
     * Sum of expense transactions in one category, in accounts of one currency, between two dates (inclusive).
     */
    public function expenseTotal(int $userId, int $categoryId, string $currency, CarbonInterface $from, CarbonInterface $to): Money;

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
