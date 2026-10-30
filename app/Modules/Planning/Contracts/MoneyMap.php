<?php

namespace App\Modules\Planning\Contracts;

use App\Modules\Identity\Models\User;
use App\Modules\Shared\Support\Money;

/**
 * Where the user's budgeted money is, per currency: in on-budget accounts, in funded bucket envelopes, and the
 * rest ready to assign. Always computed, never stored: in accounts = in buckets + ready to assign.
 */
interface MoneyMap
{
    /**
     * @return array<string, array{currency: string, inAccounts: Money, inBuckets: Money, readyToAssign: Money, overAssigned: bool}> keyed by currency
     */
    public function summary(User $user): array;

    /**
     * Money in on-budget accounts of this currency that no bucket holds (negative when over-assigned).
     */
    public function readyToAssign(User $user, string $currency): Money;
}
