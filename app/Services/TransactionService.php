<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Events\TransactionsMutated;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owns every write path for the transaction ledger. Controllers never
 * touch Transaction::create() directly so that transfer
 * double-entry, cache invalidation, and validation invariants (positive
 * amounts, distinct transfer accounts) stay enforced in exactly one place.
 */
class TransactionService
{
    public function createIncome(User $user, array $data): Transaction
    {
        return $this->createSimple($user, TransactionType::Income, $data);
    }

    public function createExpense(User $user, array $data): Transaction
    {
        return $this->createSimple($user, TransactionType::Expense, $data);
    }

    /**
     * @return array{0: Transaction, 1: Transaction} [salida, entrada]
     */
    public function createTransfer(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data) {
            $groupId = (string) Str::uuid();
            $amount = Money::of($data['amount']);

            $out = $user->transactions()->create([
                'account_id' => $data['from_account_id'],
                'type' => TransactionType::TransferOut,
                'category_id' => null,
                'amount' => $amount,
                'date' => $data['date'],
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transfer_group_id' => $groupId,
            ]);

            $in = $user->transactions()->create([
                'account_id' => $data['to_account_id'],
                'type' => TransactionType::TransferIn,
                'category_id' => null,
                'amount' => $amount,
                'date' => $data['date'],
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transfer_group_id' => $groupId,
            ]);

            $this->announce($user, collect([$out->account, $in->account]));

            return [$out, $in];
        });
    }

    public function updateSimple(Transaction $transaction, array $data): Transaction
    {
        $affectedAccounts = collect([$transaction->account]);

        $transaction->update([
            'account_id' => $data['account_id'],
            'category_id' => $data['category_id'],
            'amount' => Money::of($data['amount']),
            'date' => $data['date'],
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
        ]);

        if ($transaction->wasChanged('account_id')) {
            $affectedAccounts->push($transaction->account);
        }

        $this->announce($transaction->user, $affectedAccounts);

        return $transaction;
    }

    /**
     * Transfers only allow editing amount/date/description — changing which
     * accounts are involved means deleting and recreating the transfer, so
     * the double-entry bookkeeping never has to reconcile a partial move.
     */
    public function updateTransfer(Transaction $transaction, array $data): void
    {
        DB::transaction(function () use ($transaction, $data) {
            $sibling = Transaction::where('transfer_group_id', $transaction->transfer_group_id)
                ->whereKeyNot($transaction->id)
                ->firstOrFail();

            $attributes = [
                'amount' => Money::of($data['amount']),
                'date' => $data['date'],
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];

            $transaction->update($attributes);
            $sibling->update($attributes);

            $this->announce($transaction->user, collect([$transaction->account, $sibling->account]));
        });
    }

    public function delete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $affectedAccounts = collect([$transaction->account]);

            if ($transaction->type->isTransfer() && $transaction->transfer_group_id) {
                $sibling = Transaction::where('transfer_group_id', $transaction->transfer_group_id)
                    ->whereKeyNot($transaction->id)
                    ->first();

                if ($sibling) {
                    $affectedAccounts->push($sibling->account);
                    $sibling->delete();
                }
            }

            $transaction->delete();

            $this->announce($transaction->user, $affectedAccounts);
        });
    }

    private function createSimple(User $user, TransactionType $type, array $data): Transaction
    {
        $transaction = $user->transactions()->create([
            'account_id' => $data['account_id'],
            'type' => $type,
            'category_id' => $data['category_id'],
            'amount' => Money::of($data['amount']),
            'date' => $data['date'],
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
        ]);

        $this->announce($user, collect([$transaction->account]));

        return $transaction;
    }

    /**
     * @param  Collection<int, Account>  $accounts
     */
    private function announce(User $user, Collection $accounts): void
    {
        TransactionsMutated::dispatch($user, $accounts->unique('id'));
    }
}
