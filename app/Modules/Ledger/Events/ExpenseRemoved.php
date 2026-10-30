<?php

namespace App\Modules\Ledger\Events;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when an expense is deleted or about to be re-recorded after an edit, with the bucket it was paid
 * from until then.
 */
class ExpenseRemoved
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly int $transactionId,
        public readonly ?int $bucketId,
    ) {}
}
