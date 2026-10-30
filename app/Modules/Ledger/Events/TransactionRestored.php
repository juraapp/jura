<?php

namespace App\Modules\Ledger\Events;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Carbon;

/**
 * Fired for each leg of a transaction brought back from the Trash, so other modules can re-link what they
 * reversed when it was deleted.
 */
class TransactionRestored
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
