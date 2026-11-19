<?php

namespace App\Modules\Shared\Validation;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Foreign-key rules for planning rows that other modules' form requests accept (a raw `exists` rule bypasses
 * Eloquent scopes, so ownership is re-applied by hand, like LedgerRules).
 */
final class PlanningRules
{
    /**
     * Whether the transaction is the expense of a wish-list item that was bought. Its bucket link belongs to the
     * purchase (the item's own or its project's bucket): editing the expense must not change or drop it, or the
     * purchase would also count in a category envelope.
     */
    public static function isWishlistPurchase(int $userId, int $transactionId): bool
    {
        return DB::table('wishlist_items')
            ->where('user_id', $userId)
            ->where('purchased_transaction_id', $transactionId)
            ->exists();
    }

    /**
     * One of the user's buckets an expense can be paid from: no tracked category (a category's spending
     * already comes out of its spending bucket), not completed and, when given, in the expense's currency.
     * $keepId is the bucket an expense is already linked to: editing the expense may keep it even if the
     * bucket was completed or gained a category since.
     */
    public static function spendableBucket(int $userId, ?string $currency, ?int $keepId = null): Exists
    {
        return Rule::exists('buckets', 'id')
            ->where('user_id', $userId)
            ->where(fn (Builder $query) => $query->where(fn (Builder $open) => $open->whereNull('category_id')->whereNull('completed_on'))
                ->when($keepId !== null, fn (Builder $inner) => $inner->orWhere('id', $keepId)))
            ->when($currency !== null, fn (Exists $rule) => $rule->where('currency', $currency));
    }
}
