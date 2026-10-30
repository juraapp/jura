<?php

namespace App\Modules\Shared\Validation;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Foreign-key rules for planning rows that other modules' form requests accept (a raw `exists` rule bypasses
 * Eloquent scopes, so ownership is re-applied by hand, like LedgerRules).
 */
final class PlanningRules
{
    /**
     * One of the user's buckets an expense can be paid from: no tracked category (a category's spending
     * already comes out of its spending bucket) and, when given, in the expense's currency.
     */
    public static function spendableBucket(int $userId, ?string $currency): Exists
    {
        return Rule::exists('buckets', 'id')
            ->where('user_id', $userId)
            ->whereNull('category_id')
            ->when($currency !== null, fn (Exists $rule) => $rule->where('currency', $currency));
    }
}
