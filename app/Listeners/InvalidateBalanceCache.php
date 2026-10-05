<?php

namespace App\Listeners;

use App\Events\TransactionsMutated;
use App\Services\BalanceCalculator;

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
