<?php

namespace App\Modules\Planning\Listeners;

use App\Modules\Ledger\Events\TransactionPurged;
use App\Modules\Planning\Services\PlannedEventService;
use App\Modules\Planning\Services\WishlistService;

/**
 * A transaction is removed for good: drop the links plans and wish-list items kept to it.
 */
class UnlinkPurgedRecords
{
    public function __construct(
        private readonly PlannedEventService $plans,
        private readonly WishlistService $wishlist,
    ) {}

    public function handle(TransactionPurged $event): void
    {
        $this->plans->transactionPurged($event->user, $event->transactionId);
        $this->wishlist->transactionPurged($event->user, $event->transactionId);
    }
}
