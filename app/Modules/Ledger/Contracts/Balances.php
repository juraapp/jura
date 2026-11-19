<?php

namespace App\Modules\Ledger\Contracts;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Shared\Support\Money;
use Illuminate\Support\Collection;

/**
 * Derived account balances and net worth (never stored, always computed from the ledger).
 */
interface Balances
{
    public function accountBalance(Account $account): Money;

    /**
     * @return Collection<string, Money> net worth keyed by currency code
     */
    public function netWorthByCurrency(User $user): Collection;

    /**
     * The accounts whose money is budgeted: active, on budget and not deleted, each with its balance
     * (every recorded transaction counts, future-dated ones included).
     *
     * @return Collection<int, array{id: int, currency: string, balance: Money}>
     */
    public function budgetBalances(User $user): Collection;

    /**
     * Active accounts that earn interest, with their current balance.
     *
     * @return Collection<int, array{id: int, currency: string, rate: string, compounding: string, balance: Money}>
     */
    public function yieldBearing(User $user): Collection;

    public function forgetAccount(Account $account): void;
}
