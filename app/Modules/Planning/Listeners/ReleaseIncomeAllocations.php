<?php

namespace App\Modules\Planning\Listeners;

use App\Modules\Ledger\Events\IncomeRemoved;
use App\Modules\Planning\Services\IncomeAllocator;

class ReleaseIncomeAllocations
{
    public function __construct(private readonly IncomeAllocator $allocator) {}

    public function handle(IncomeRemoved $event): void
    {
        $this->allocator->release($event->user, $event->transactionId);
    }
}
