<?php

namespace App\Modules\Ledger\Events;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when an income transaction is deleted or about to be re-recorded after an edit.
 */
class IncomeRemoved
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly int $transactionId,
    ) {}
}
