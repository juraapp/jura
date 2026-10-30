<?php

namespace App\Modules\Ledger\Events;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Carbon;

/**
 * Fired for each leg of a transaction moved to the Trash (single or bulk delete), so other modules can
 * reverse what depended on it (a realized plan, a wish-list purchase). The row still exists, soft-deleted.
 */
class TransactionDeleted
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly int $transactionId,
        public readonly TransactionType $type,
        public readonly int $accountId,
        public readonly Money $amount,
        public readonly Carbon $date,
    ) {}
}
