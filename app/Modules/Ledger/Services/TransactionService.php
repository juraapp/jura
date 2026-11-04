<?php

namespace App\Modules\Ledger\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\RecordsTransactions;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Ledger\Events\ExpenseRecorded;
use App\Modules\Ledger\Events\ExpenseRemoved;
use App\Modules\Ledger\Events\IncomeRecorded;
use App\Modules\Ledger\Events\IncomeRemoved;
use App\Modules\Ledger\Events\TransactionDeleted;
use App\Modules\Ledger\Events\TransactionPurged;
use App\Modules\Ledger\Events\TransactionRestored;
use App\Modules\Ledger\Events\TransactionsMutated;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
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

    public function record(User $user, TransactionType $type, array $data, bool $autoPosted = false): int
    {
        $data['is_recurring_generated'] = $autoPosted;

        return match ($type) {
            TransactionType::Income => $this->createIncome($user, $data)->id,
            TransactionType::Expense => $this->createExpense($user, $data)->id,
            default => throw new \InvalidArgumentException('Only income and expense can be recorded this way.'),
        };
    }

    public function recordTransfer(User $user, array $data, bool $autoPosted = false): int
    {
        [$out] = $this->createTransfer($user, $data + ['is_recurring_generated' => $autoPosted]);

        return $out->id;
    }

    /**
     * Removes a transaction for good (a system undo, so it does not land in the Trash).
     */
    public function forget(User $user, int $transactionId): void
    {
        $transaction = Transaction::forUser($user)->find($transactionId);

        if ($transaction !== null) {
            DB::transaction(function () use ($transaction) {
                $this->delete($transaction);
                $this->forceDelete($transaction);
            });
        }
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
                'time' => $data['time'] ?? null,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transfer_group_id' => $groupId,
                'is_recurring_generated' => (bool) ($data['is_recurring_generated'] ?? false),
            ]);

            $in = $user->transactions()->create([
                'account_id' => $data['to_account_id'],
                'type' => TransactionType::TransferIn,
                'category_id' => null,
                'amount' => $amount,
                'date' => $data['date'],
                'time' => $data['time'] ?? null,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transfer_group_id' => $groupId,
                'is_recurring_generated' => (bool) ($data['is_recurring_generated'] ?? false),
            ]);

            $this->announce($user, collect([$out->account, $in->account]));

            return [$out, $in];
        });
    }

    /**
     * Fields whose change means an income has to be released and allocated to buckets again.
     * Editing only the description, notes, time, category or payment method leaves its buckets alone.
     */
    private const MONEY_FIELDS = ['account_id', 'amount', 'date', 'type'];

    /**
     * Fields whose change means an expense's spend from its bucket has to be redone.
     */
    private const EXPENSE_LINK_FIELDS = ['account_id', 'amount', 'date', 'type', 'bucket_id'];

    public function updateSimple(Transaction $transaction, array $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $data) {
            $affectedAccounts = collect([$transaction->account]);

            $transaction->fill([
                'account_id' => $data['account_id'],
                'category_id' => $data['category_id'],
                'amount' => Money::of($data['amount']),
                'date' => $data['date'],
                'time' => $data['time'] ?? null,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                // Left out (a bulk move), the expense keeps the bucket it was paid from.
                'bucket_id' => $transaction->type === TransactionType::Expense
                    ? (array_key_exists('bucket_id', $data) ? $data['bucket_id'] : $transaction->bucket_id)
                    : null,
            ]);

            $reallocate = $transaction->type === TransactionType::Income && $transaction->isDirty(self::MONEY_FIELDS);
            $respend = $transaction->type === TransactionType::Expense && $transaction->isDirty(self::EXPENSE_LINK_FIELDS);

            if ($reallocate) {
                IncomeRemoved::dispatch($transaction->user, $transaction->id);
            }

            if ($respend) {
                ExpenseRemoved::dispatch($transaction->user, $transaction->id, $transaction->getOriginal('bucket_id'));
            }

            $transaction->save();

            if ($transaction->wasChanged('account_id')) {
                $affectedAccounts->push($transaction->account()->first());
            }

            $this->announce($transaction->user, $affectedAccounts);

            if ($reallocate) {
                $this->announceIncome($transaction->refresh());
            }

            if ($respend) {
                $this->announceExpense($transaction->refresh());
            }

            return $transaction;
        });
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
                'time' => $data['time'] ?? null,
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
                    $this->announceLeg(TransactionDeleted::class, $sibling);
                }
            }

            $transaction->delete();
            $this->announceLeg(TransactionDeleted::class, $transaction);

            if ($transaction->type === TransactionType::Income) {
                IncomeRemoved::dispatch($transaction->user, $transaction->id);
            }

            if ($transaction->type === TransactionType::Expense) {
                ExpenseRemoved::dispatch($transaction->user, $transaction->id, $transaction->bucket_id);
            }

            $this->announce($transaction->user, $affectedAccounts);
        });
    }

    /**
     * Moves a simple transaction to another account and/or category. Transfers, a category of
     * the wrong kind and an account in another currency are left alone. Returns whether anything changed.
     */
    public function reassign(Transaction $transaction, ?int $accountId, ?Category $category): bool
    {
        if ($transaction->type->isTransfer()) {
            return false;
        }

        if ($category !== null && $category->type->value !== $transaction->type->value) {
            $category = null;
        }

        if ($accountId !== null && Account::whereKey($accountId)->value('currency') !== $transaction->account->currency) {
            $accountId = null;
        }

        // An off-budget account's money is not in any bucket, so an expense moved there is no longer paid from one.
        $bucketId = $accountId !== null && Account::whereKey($accountId)->value('on_budget') === false ? null : $transaction->bucket_id;

        $newAccountId = $accountId ?? $transaction->account_id;
        $newCategoryId = $category?->id ?? $transaction->category_id;

        if ($newAccountId === $transaction->account_id && $newCategoryId === $transaction->category_id) {
            return false;
        }

        $this->updateSimple($transaction, [
            'account_id' => $newAccountId,
            'category_id' => $newCategoryId,
            'amount' => $transaction->amount->toDecimalString(),
            'date' => $transaction->date->toDateString(),
            'time' => $transaction->time,
            'description' => $transaction->description,
            'notes' => $transaction->notes,
            'payment_method' => $transaction->payment_method?->value,
            'bucket_id' => $bucketId,
        ]);

        return true;
    }

    /**
     * Brings a deleted transaction (and the other leg of a transfer) back.
     */
    public function restore(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $legs = $this->trashedLegs($transaction);
            $legs->each->restore();
            $legs->each(fn (Transaction $leg) => $this->announceLeg(TransactionRestored::class, $leg));

            $this->announce($transaction->user, $legs->map->account);

            $legs->filter(fn (Transaction $leg) => $leg->type === TransactionType::Income)
                ->each(fn (Transaction $leg) => $this->announceIncome($leg->refresh()));
            $legs->filter(fn (Transaction $leg) => $leg->type === TransactionType::Expense)
                ->each(fn (Transaction $leg) => $this->announceExpense($leg->refresh()));
        });
    }

    /**
     * Removes a deleted transaction (and the other leg of a transfer) for good.
     */
    public function forceDelete(Transaction $transaction): void
    {
        DB::transaction(fn () => $this->trashedLegs($transaction)->each(function (Transaction $leg) {
            $this->announceLeg(TransactionPurged::class, $leg);
            $leg->forceDelete();
        }));
    }

    /**
     * @return Collection<int, Transaction>
     */
    private function trashedLegs(Transaction $transaction): Collection
    {
        $legs = collect([$transaction]);

        if ($transaction->type->isTransfer() && $transaction->transfer_group_id) {
            $legs = Transaction::onlyTrashed()
                ->where('transfer_group_id', $transaction->transfer_group_id)
                ->get();
        }

        return $legs->each(fn (Transaction $leg) => $leg->loadMissing('account'));
    }

    private function createSimple(User $user, TransactionType $type, array $data): Transaction
    {
        return DB::transaction(function () use ($user, $type, $data) {
            $transaction = $user->transactions()->create([
                'account_id' => $data['account_id'],
                'type' => $type,
                'category_id' => $data['category_id'],
                'amount' => Money::of($data['amount']),
                'date' => $data['date'],
                'time' => $data['time'] ?? null,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'bucket_id' => $type === TransactionType::Expense ? ($data['bucket_id'] ?? null) : null,
                'is_recurring_generated' => (bool) ($data['is_recurring_generated'] ?? false),
            ]);

            $this->announce($user, collect([$transaction->account]));
            $this->announceIncome($transaction);
            $this->announceExpense($transaction);

            return $transaction;
        });
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

    private function announceExpense(Transaction $transaction): void
    {
        if ($transaction->type !== TransactionType::Expense) {
            return;
        }

        ExpenseRecorded::dispatch(
            $transaction->user,
            $transaction->id,
            $transaction->account_id,
            $transaction->amount,
            $transaction->account->currency,
            $transaction->date,
            $transaction->bucket_id,
        );
    }

    /**
     * @param  class-string<TransactionDeleted|TransactionRestored|TransactionPurged>  $event
     */
    private function announceLeg(string $event, Transaction $leg): void
    {
        $event::dispatch($leg->user, $leg->id, $leg->type, $leg->account_id, $leg->amount, $leg->date);
    }

    /**
     * @param  Collection<int, Account>  $accounts
     */
    private function announce(User $user, Collection $accounts): void
    {
        TransactionsMutated::dispatch($user, $accounts->unique('id'));
    }
}
