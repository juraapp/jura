<?php

namespace App\Modules\Planning\Services;

use App\Modules\Ledger\Contracts\RecordsTransactions;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Enums\WishlistStatus;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Shared\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Buying a wish-list item: the real expense and the matching spend from its
 * bucket happen together and are undone together.
 */
class WishlistService
{
    public function __construct(
        private readonly RecordsTransactions $transactions,
        private readonly BucketLedger $funds,
    ) {}

    /**
     * @param  array{account_id: int, category_id: int, amount?: mixed, date?: string|null}  $data
     */
    public function purchase(WishlistItem $item, array $data): int
    {
        return DB::transaction(function () use ($item, $data) {
            $amount = Money::of($data['amount'] ?? $item->price->toDecimalString());
            $date = isset($data['date']) ? Carbon::parse($data['date']) : today();

            $transactionId = $this->transactions->record($item->user, TransactionType::Expense, [
                'account_id' => $data['account_id'],
                'category_id' => $data['category_id'],
                'amount' => $amount->toDecimalString(),
                'date' => $date->toDateString(),
                'description' => $item->name,
                'notes' => $item->notes,
            ]);

            $allocationId = null;
            if ($item->bucket !== null) {
                $covered = $this->smaller($amount, $this->funds->available($item->bucket));

                if ($covered->isPositive()) {
                    $allocationId = $this->funds
                        ->credit($item->bucket, $covered->negate(), AllocationSource::Spend, $transactionId, $date, $item->name)
                        ->id;
                }
            }

            if ($item->bucket !== null && $item->bucket->completes_on_purchase && ! $item->bucket->isCompletedOn($date)) {
                $this->funds->complete($item->bucket, $date);
            }

            $item->update([
                'status' => WishlistStatus::Purchased,
                'purchased_on' => $date->toDateString(),
                'purchased_transaction_id' => $transactionId,
                'purchase_allocation_id' => $allocationId,
            ]);

            return $transactionId;
        });
    }

    public function undoPurchase(WishlistItem $item): void
    {
        DB::transaction(function () use ($item) {
            if ($item->bucket !== null && $item->bucket->completes_on_purchase
                && $item->bucket->completed_on?->toDateString() === $item->purchased_on?->toDateString()) {
                $this->funds->reopen($item->bucket);
            }

            if ($item->purchase_allocation_id !== null) {
                Allocation::forUser($item->user_id)->whereKey($item->purchase_allocation_id)->delete();
            }

            if ($item->purchased_transaction_id !== null) {
                $this->transactions->forget($item->user, $item->purchased_transaction_id);
            }

            $item->update([
                'status' => WishlistStatus::Wanted,
                'purchased_on' => null,
                'purchased_transaction_id' => null,
                'purchase_allocation_id' => null,
            ]);
        });
    }

    private function smaller(Money $a, Money $b): Money
    {
        return $a->lessThan($b) ? $a : $b;
    }
}
