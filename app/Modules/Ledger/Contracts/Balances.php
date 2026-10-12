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

    public function netWorth(User $user): Money;

    /**
     * @return Collection<string, Money> net worth keyed by currency code
     */
    public function netWorthByCurrency(User $user): Collection;

    /**
     * Active accounts that earn interest, with their current balance.
     *
     * @return Collection<int, array{id: int, currency: string, rate: string, compounding: string, balance: Money}>
     */
    public function yieldBearing(User $user): Collection;

    public function forgetAccount(Account $account): void;
}
