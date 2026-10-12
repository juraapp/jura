<?php

namespace App\Modules\Ledger\Contracts;

use App\Modules\Ledger\Enums\CategoryType;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Read-only questions other modules ask the ledger. Everything is scoped to the current user.
 */
interface LedgerQuery
{
    /**
     * Sum of expense transactions in one category, in accounts of one currency, between two dates (inclusive).
     */
    public function expenseTotal(int $categoryId, string $currency, CarbonInterface $from, CarbonInterface $to): Money;

    /**
     * @return Collection<int, \stdClass> active accounts (id, name) ordered by name
     */
    public function activeAccountOptions(): Collection;

    /**
     * @param  list<string>  $columns
     * @return Collection<int, \stdClass> categories of one type ordered by name
     */
    public function categoryOptions(CategoryType $type, array $columns = ['id', 'name']): Collection;
}
