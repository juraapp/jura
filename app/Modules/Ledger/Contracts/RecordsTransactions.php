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
     * Record a plain income or expense and return the new transaction's id.
     *
     * @param  array<string, mixed>  $data  account_id, category_id, amount, date, description, notes
     */
    public function record(User $user, TransactionType $type, array $data): int;

    /**
     * Record an income or expense generated from a recurring template.
     *
     * @param  array<string, mixed>  $data  account_id, category_id, amount, date, description
     */
    public function recordRecurring(User $user, TransactionType $type, array $data, int $recurringTransactionId): void;

    /**
     * Remove for good a transaction this user previously recorded (for example to undo a purchase). It does not go
     * to the Trash, but TransactionDeleted and TransactionPurged still fire.
     */
    public function forget(User $user, int $transactionId): void;
}
