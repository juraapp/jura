<?php

namespace App\Modules\Planning\Listeners;

use App\Modules\Ledger\Events\TransactionDeleted;
use App\Modules\Planning\Services\PlannedEventService;
use App\Modules\Planning\Services\WishlistService;

/**
 * A transaction went to the Trash: reopen the plan it realized and reverse the purchase it paid for.
 */
class ReverseLinkedRecords
{
    public function __construct(
        private readonly PlannedEventService $plans,
        private readonly WishlistService $wishlist,
    ) {}

    public function handle(TransactionDeleted $event): void
    {
        $this->plans->transactionDeleted($event->user, $event->transactionId);
        $this->wishlist->transactionDeleted($event->user, $event->transactionId);
    }
}
