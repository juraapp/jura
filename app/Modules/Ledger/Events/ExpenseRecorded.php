<?php

namespace App\Modules\Ledger\Events;

use App\Modules\Identity\Models\User;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Carbon;

/**
 * Fired after an expense is created, restored from the Trash or re-recorded after an edit of its account,
 * amount, date or bucket, so other modules can react, for example by spending from the bucket that paid for it.
 */
class ExpenseRecorded
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly int $transactionId,
        public readonly int $accountId,
        public readonly Money $amount,
        public readonly string $currency,
        public readonly Carbon $date,
        public readonly ?int $bucketId,
    ) {}
}
