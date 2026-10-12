<?php

namespace App\Modules\Ledger\Events;

use App\Modules\Identity\Models\User;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Carbon;

/**
 * Fired after an income transaction is created (or re-created after an edit),
 * so other modules can react, for example by filling buckets.
 */
class IncomeRecorded
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly int $transactionId,
        public readonly int $accountId,
        public readonly Money $amount,
        public readonly string $currency,
        public readonly Carbon $date,
    ) {}
}
