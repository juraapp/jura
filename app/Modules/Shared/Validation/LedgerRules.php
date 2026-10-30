<?php

namespace App\Modules\Shared\Validation;

use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Foreign-key rules for ledger rows that any module's form requests accept. A raw `exists` rule bypasses
 * Eloquent scopes, so these re-apply ownership and soft deletes by hand (see docs/domain-model.md).
 */
final class LedgerRules
{
    /**
     * One of the user's accounts that is not in the trash. With $activeOnly, archived accounts are rejected
     * too, except $keepId (the account a record already uses, so editing it doesn't force a move).
     */
    public static function account(int $userId, bool $activeOnly = false, ?int $keepId = null): Exists
    {
        $rule = Rule::exists('accounts', 'id')->where('user_id', $userId)->whereNull('deleted_at');

        if ($activeOnly) {
            $rule->where(fn (Builder $query) => $query->where('is_active', true)
                ->when($keepId !== null, fn (Builder $inner) => $inner->orWhere('id', $keepId)));
        }

        return $rule;
    }

    /**
     * A category the user can see (their own or a system one) that is not in the trash. With a $type
     * ('income' or 'expense'), it must also be of that kind, so an income is never filed under an expense.
     */
    public static function category(int $userId, ?string $type = null): Exists
    {
        return Rule::exists('categories', 'id')
            ->where(fn (Builder $query) => $query->where('user_id', $userId)->orWhereNull('user_id'))
            ->whereNull('deleted_at')
            ->when($type !== null, fn (Exists $rule) => $rule->where('type', $type));
    }
}
