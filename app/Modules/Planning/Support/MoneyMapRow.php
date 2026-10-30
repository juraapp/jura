<?php

namespace App\Modules\Planning\Support;

use App\Modules\Shared\Support\Money;

/**
 * Where one currency's budgeted money is: in the accounts, in bucket envelopes, and the rest ready to assign.
 */
final readonly class MoneyMapRow
{
    public function __construct(
        public string $currency,
        public Money $inAccounts,
        public Money $inBuckets,
        public Money $readyToAssign,
    ) {}

    /**
     * More money sits in buckets than the accounts hold.
     */
    public function isOverAssigned(): bool
    {
        return $this->readyToAssign->isNegative();
    }
}
