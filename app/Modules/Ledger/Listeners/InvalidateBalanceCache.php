<?php

namespace App\Modules\Ledger\Listeners;

use App\Modules\Ledger\Events\TransactionsMutated;
use App\Modules\Ledger\Services\BalanceCalculator;

class InvalidateBalanceCache
{
    public function __construct(private readonly BalanceCalculator $balances) {}

    public function handle(TransactionsMutated $event): void
    {
        foreach ($event->accounts as $account) {
            $this->balances->forgetAccount($account);
        }
    }
}
