<?php

namespace App\Modules\Ledger\Contracts;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Enums\TransactionType;

/**
 * The only way other modules create transactions.
 */
interface RecordsTransactions
{
    /**
     * Record an income or expense generated from a recurring template.
     *
     * @param  array<string, mixed>  $data  account_id, category_id, amount, date, description
     */
    public function recordRecurring(User $user, TransactionType $type, array $data, int $recurringTransactionId): void;
}
