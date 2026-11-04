<?php

namespace App\Modules\Ledger\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when an account enters or leaves the budget: it is in the budget while it is on budget and not archived.
 * Carries ids only; Planning uses it to stop (or start again) taking the account's expenses out of the buckets
 * they were paid from.
 */
class AccountBudgetChanged
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly int $accountId,
        public readonly bool $inBudget,
    ) {}
}
