<?php

namespace App\Modules\Planning\Listeners;

use App\Modules\Ledger\Events\IncomeRecorded;
use App\Modules\Planning\Services\IncomeAllocator;

class AllocateIncome
{
    public function __construct(private readonly IncomeAllocator $allocator) {}

    public function handle(IncomeRecorded $event): void
    {
        $this->allocator->allocate($event->user, $event->transactionId, $event->accountId, $event->amount, $event->currency, $event->date);
    }
}
