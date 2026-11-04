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
     * Record a plain income or expense and return the new transaction's id. $autoPosted marks a transaction
     * the app recorded by itself (an auto-posting plan), shown as "Auto-posted".
     *
     * @param  array<string, mixed>  $data  account_id, category_id, amount, date, description, notes
     */
    public function record(User $user, TransactionType $type, array $data, bool $autoPosted = false): int;

    /**
     * Record a transfer between two of the user's accounts in the same currency (two linked rows) and return
     * the id of its outgoing leg, which identifies the transfer.
     *
     * @param  array<string, mixed>  $data  from_account_id, to_account_id, amount, date, description, notes
     */
    public function recordTransfer(User $user, array $data, bool $autoPosted = false): int;

    /**
     * Remove for good a transaction this user previously recorded (for example to undo a purchase). It does not go
     * to the Trash, but TransactionDeleted and TransactionPurged still fire.
     */
    public function forget(User $user, int $transactionId): void;
}
