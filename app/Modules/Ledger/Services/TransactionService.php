<?php

namespace App\Modules\Ledger\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\RecordsTransactions;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Ledger\Events\IncomeRecorded;
use App\Modules\Ledger\Events\IncomeRemoved;
use App\Modules\Ledger\Events\TransactionsMutated;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Shared\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owns every write path for the transaction ledger. Controllers never
 * touch Transaction::create() directly so that transfer
 * double-entry, cache invalidation, and validation invariants (positive
 * amounts, distinct transfer accounts) stay enforced in exactly one place.
 */
class TransactionService implements RecordsTransactions
{
    public function createIncome(User $user, array $data): Transaction
    {
        return $this->createSimple($user, TransactionType::Income, $data);
    }

    public function createExpense(User $user, array $data): Transaction
    {
        return $this->createSimple($user, TransactionType::Expense, $data);
    }

    public function record(User $user, TransactionType $type, array $data): int
    {
        return match ($type) {
            TransactionType::Income => $this->createIncome($user, $data)->id,
            TransactionType::Expense => $this->createExpense($user, $data)->id,
            default => throw new \InvalidArgumentException('Only income and expense can be recorded this way.'),
        };
    }

    public function forget(User $user, int $transactionId): void
    {
        $transaction = Transaction::forUser($user)->find($transactionId);

        if ($transaction !== null) {
            $this->delete($transaction);
        }
    }

    public function recordRecurring(User $user, TransactionType $type, array $data, int $recurringTransactionId): void
    {
        $transaction = $type === TransactionType::Income
            ? $this->createIncome($user, $data)
            : $this->createExpense($user, $data);

        $transaction->update([
            'is_recurring_generated' => true,
            'recurring_transaction_id' => $recurringTransactionId,
        ]);
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
        $isIncome = $transaction->type === TransactionType::Income;

        if ($isIncome) {
            IncomeRemoved::dispatch($transaction->user, $transaction->id);
        }

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

        if ($isIncome) {
            $this->announceIncome($transaction->refresh());
        }

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

            if ($transaction->type === TransactionType::Income) {
                IncomeRemoved::dispatch($transaction->user, $transaction->id);
            }

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
        $this->announceIncome($transaction);

        return $transaction;
    }

    private function announceIncome(Transaction $transaction): void
    {
        if ($transaction->type !== TransactionType::Income) {
            return;
        }

        IncomeRecorded::dispatch(
            $transaction->user,
            $transaction->id,
            $transaction->account_id,
            $transaction->amount,
            $transaction->account->currency,
            $transaction->date,
        );
    }

    /**
     * @param  Collection<int, Account>  $accounts
     */
    private function announce(User $user, Collection $accounts): void
    {
        TransactionsMutated::dispatch($user, $accounts->unique('id'));
    }
}
