<?php

namespace App\Modules\Shared\Validation;

/**
 * The one validation for money typed by a user. `decimal:0,2` rejects exponents ("1e3") and sub-cent
 * input (0.001 would otherwise be stored as 0.00), and the bounds keep values inside decimal(15,2).
 */
final class MoneyRules
{
    public const MAX = '9999999999999.99';

    /**
     * @return list<string> an amount greater than zero
     */
    public static function positive(): array
    {
        return ['numeric', 'decimal:0,2', 'gt:0', 'max:'.self::MAX];
    }

    /**
     * @return list<string> zero or more
     */
    public static function nonNegative(): array
    {
        return ['numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX];
    }

    /**
     * @return list<string> any amount, negative included (opening balances, adjustments)
     */
    public static function signed(): array
    {
        return ['numeric', 'decimal:0,2', 'min:-'.self::MAX, 'max:'.self::MAX];
    }
}
