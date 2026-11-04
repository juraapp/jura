<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Contracts\RecordsTransactions;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Enums\WishlistStatus;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Buying a wish-list item: the real expense and the matching spend from its
 * bucket happen together and are undone together. The expense is linked to the
 * bucket (when paid from an on-budget account), so it never also counts in a
 * category envelope; the bucket pays what it has and the rest comes out of
 * ready to assign. Deleting the expense from the
 * ledger reverses the purchase; restoring it from the Trash applies it again.
 */
class WishlistService
{
    public function __construct(
        private readonly RecordsTransactions $transactions,
        private readonly BucketLedger $funds,
        private readonly LedgerQuery $ledger,
        private readonly ExpenseSpending $spending,
    ) {}

    /**
     * @param  array{account_id: int, category_id: int, amount?: mixed, date?: string|null}  $data
     */
    public function purchase(WishlistItem $item, array $data): int
    {
        return DB::transaction(function () use ($item, $data) {
            $amount = Money::of($data['amount'] ?? $item->price->toDecimalString());
            $date = isset($data['date']) ? Carbon::parse($data['date']) : Carbon::instance($item->user->today());

            $paidFromBucket = $item->bucket !== null && $this->ledger->isOnBudget($item->user_id, (int) $data['account_id']);

            $transactionId = $this->spending->withoutSpending(fn () => $this->transactions->record($item->user, TransactionType::Expense, [
                'account_id' => $data['account_id'],
                'category_id' => $data['category_id'],
                'amount' => $amount->toDecimalString(),
                'date' => $date->toDateString(),
                'description' => $item->name,
                'notes' => $item->notes,
                'bucket_id' => $paidFromBucket ? $item->bucket_id : null,
            ]));

            $this->applyPurchase($item, $transactionId, $amount, $date, (int) $data['account_id']);

            return $transactionId;
        });
    }

    /**
     * Undoes a purchase: the spend goes back to the bucket and the expense is removed for good
     * (a system revert, so it does not land in the Trash).
     */
    public function undoPurchase(WishlistItem $item): void
    {
        DB::transaction(function () use ($item) {
            $transactionId = $item->purchased_transaction_id;

            $this->reversePurchase($item);
            $item->update(['purchased_transaction_id' => null]);

            if ($transactionId !== null) {
                $this->transactions->forget($item->user, $transactionId);
            }
        });
    }

    /**
     * The purchase's expense went to the Trash: the item is wanted again and its bucket gets the money back.
     * The transaction id is kept so restoring the expense can apply the purchase again.
     */
    public function transactionDeleted(User $user, int $transactionId): void
    {
        WishlistItem::forUser($user)
            ->where('purchased_transaction_id', $transactionId)
            ->where('status', WishlistStatus::Purchased->value)
            ->get()
            ->each(fn (WishlistItem $item) => $this->reversePurchase($item));
    }

    /**
     * The purchase's expense is back from the Trash: if the item is still wanted (not bought again in the
     * meantime), it is purchased again with the expense's amount and date.
     */
    public function transactionRestored(User $user, int $transactionId, Money $amount, CarbonInterface $date, int $accountId): void
    {
        WishlistItem::forUser($user)
            ->where('purchased_transaction_id', $transactionId)
            ->where('status', WishlistStatus::Wanted->value)
            ->get()
            ->each(fn (WishlistItem $item) => $this->applyPurchase($item, $transactionId, $amount, Carbon::instance($date), $accountId));
    }

    /**
     * The purchase's expense is gone for good: forget the link (and reverse a purchase still marked as made).
     */
    public function transactionPurged(User $user, int $transactionId): void
    {
        WishlistItem::forUser($user)
            ->where('purchased_transaction_id', $transactionId)
            ->get()
            ->each(function (WishlistItem $item) {
                if ($item->status === WishlistStatus::Purchased) {
                    $this->reversePurchase($item);
                }

                $item->update(['purchased_transaction_id' => null]);
            });
    }

    /**
     * Records the purchase: the bucket pays what it has, up to the price (the rest comes out of ready to assign).
     * An off-budget account's money is not budgeted, so a purchase paid from one takes nothing from the bucket.
     */
    private function applyPurchase(WishlistItem $item, int $transactionId, Money $amount, Carbon $date, int $accountId): void
    {
        $allocationId = null;
        if ($item->bucket !== null && $this->ledger->isOnBudget($item->user_id, $accountId)) {
            $covered = $this->smaller($amount, $this->funds->available($item->bucket));

            if ($covered->isPositive()) {
                $allocationId = $this->funds
                    ->credit($item->bucket, $covered->negate(), AllocationSource::Spend, $transactionId, $date, $item->name)
                    ->id;
            }
        }

        $completion = null;
        if ($item->bucket !== null && $item->bucket->completes_on_purchase && ! $item->bucket->isCompletedOn($date)) {
            $completion = $this->funds->complete($item->bucket, $date);
        }

        $item->update([
            'status' => WishlistStatus::Purchased,
            'purchased_on' => $date->toDateString(),
            'purchased_transaction_id' => $transactionId,
            'purchase_allocation_id' => $allocationId,
            'completion_allocation_ids' => $completion ?: null,
        ]);
    }

    /**
     * Gives the bucket its money back and marks the item wanted. Keeps purchased_transaction_id.
     */
    private function reversePurchase(WishlistItem $item): void
    {
        if ($item->bucket !== null && $item->bucket->completes_on_purchase
            && $item->bucket->completed_on?->toDateString() === $item->purchased_on?->toDateString()) {
            $this->funds->reopen($item->bucket);
            $this->takeBackLeftover($item);
        }

        if ($item->purchase_allocation_id !== null) {
            Allocation::forUser($item->user_id)->whereKey($item->purchase_allocation_id)->delete();

            if ($item->bucket !== null) {
                $this->funds->forgetFundingIfUnused($item->bucket);
            }
        }

        $item->update([
            'status' => WishlistStatus::Wanted,
            'purchased_on' => null,
            'purchase_allocation_id' => null,
            'completion_allocation_ids' => null,
        ]);
    }

    /**
     * Brings back the leftover the purchase's completion moved out of the bucket: from ready to assign in full, or
     * from the overflow bucket up to what it still holds (what it already used stays used).
     */
    private function takeBackLeftover(WishlistItem $item): void
    {
        $rows = Allocation::forUser($item->user_id)->whereKey($item->completion_allocation_ids ?? [])->get();

        if ($rows->isEmpty()) {
            return;
        }

        $moved = $rows->first(fn (Allocation $row) => $row->bucket_id !== $item->bucket_id);
        $target = $moved !== null ? Bucket::forUser($item->user_id)->find($moved->bucket_id) : null;

        if ($moved === null || $target === null) {
            Allocation::forUser($item->user_id)->whereKey($rows->modelKeys())->where('source', AllocationSource::Ready->value)->delete();

            return;
        }

        $back = $this->smaller($moved->amount, $this->funds->available($target));

        if ($back->equals($moved->amount)) {
            Allocation::forUser($item->user_id)->whereKey($rows->modelKeys())->delete();
        } elseif ($back->isPositive()) {
            $this->funds->move($target, $item->bucket, $back, null, AllocationSource::Carry, $item->purchased_on);
        }
    }

    private function smaller(Money $a, Money $b): Money
    {
        return $a->lessThan($b) ? $a : $b;
    }
}
