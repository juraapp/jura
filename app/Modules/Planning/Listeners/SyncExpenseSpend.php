<?php

namespace App\Modules\Planning\Listeners;

use App\Modules\Ledger\Events\ExpenseRecorded;
use App\Modules\Ledger\Events\ExpenseRemoved;
use App\Modules\Planning\Services\ExpenseSpending;

/**
 * Keeps the spend of an expense paid from a bucket in step with the expense.
 */
class SyncExpenseSpend
{
    public function __construct(private readonly ExpenseSpending $spending) {}

    public function handleRecorded(ExpenseRecorded $event): void
    {
        $this->spending->apply($event->user, $event->transactionId, $event->accountId, $event->amount, $event->date, $event->bucketId);
    }

    public function handleRemoved(ExpenseRemoved $event): void
    {
        $this->spending->remove($event->user, $event->transactionId, $event->bucketId);
    }
}
