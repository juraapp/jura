<?php

namespace App\Modules\Planning\Listeners;

use App\Modules\Ledger\Events\TransactionRestored;
use App\Modules\Planning\Services\PlannedEventService;
use App\Modules\Planning\Services\WishlistService;

/**
 * A transaction came back from the Trash: close the plan and re-apply the purchase it was linked to,
 * unless they were realized or bought again in the meantime.
 */
class RelinkRestoredRecords
{
    public function __construct(
        private readonly PlannedEventService $plans,
        private readonly WishlistService $wishlist,
    ) {}

    public function handle(TransactionRestored $event): void
    {
        $this->plans->transactionRestored($event->user, $event->transactionId);
        $this->wishlist->transactionRestored($event->user, $event->transactionId, $event->amount, $event->date, $event->accountId);
    }
}
