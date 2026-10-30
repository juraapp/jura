<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * An expense paid from a bucket spends its full amount from it (a `spend` allocation tied to the transaction),
 * even when that takes the bucket below zero: an overspent envelope shows as such. Wish-list purchases keep
 * their own spend row (WishlistService), so they are left alone here.
 */
class ExpenseSpending
{
    public function __construct(
        private readonly BucketLedger $funds,
        private readonly LedgerQuery $ledger,
    ) {}

    public function apply(User $user, int $transactionId, int $accountId, Money $amount, CarbonInterface $date, ?int $bucketId): void
    {
        if ($bucketId === null || $this->isPurchase($user, $transactionId) || ! $this->ledger->isOnBudget($user->id, $accountId)) {
            return;
        }

        $bucket = Bucket::forUser($user)->find($bucketId);
        if ($bucket === null) {
            return;
        }

        $this->rows($user, $transactionId, $bucketId)->delete();
        $this->funds->credit($bucket, $amount->negate(), AllocationSource::Spend, $transactionId, $date);
    }

    public function remove(User $user, int $transactionId, ?int $bucketId): void
    {
        if ($bucketId === null || $this->isPurchase($user, $transactionId)) {
            return;
        }

        $this->rows($user, $transactionId, $bucketId)->delete();
    }

    private function isPurchase(User $user, int $transactionId): bool
    {
        return WishlistItem::forUser($user)->where('purchased_transaction_id', $transactionId)->exists();
    }

    /**
     * @return Builder<Allocation>
     */
    private function rows(User $user, int $transactionId, int $bucketId)
    {
        return Allocation::forUser($user)
            ->where('bucket_id', $bucketId)
            ->where('source', AllocationSource::Spend->value)
            ->where('source_id', $transactionId);
    }
}
