<?php

namespace App\Events;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

/**
 * Fired after any create/update/delete on a transaction or transfer.
 * Carries every account touched by the mutation (a transfer touches two)
 * so listeners can invalidate balances and re-check budgets without caring
 * whether this was a create, update, or delete.
 */
class TransactionsMutated
{
    use Dispatchable;

    /**
     * @param  Collection<int, Account>  $accounts
     */
    public function __construct(
        public readonly User $user,
        public readonly Collection $accounts,
    ) {}
}
