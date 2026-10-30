<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Contracts\MoneyMap;
use App\Modules\Shared\Support\Money;

/**
 * Warns before money is put in a bucket that is not ready to assign. The warning is soft: the user may confirm
 * and over-assign anyway (the money map then shows it in red).
 */
class AssignGuard
{
    public function __construct(private readonly MoneyMap $moneyMap) {}

    /**
     * How far below zero ready to assign would go if this much more went into buckets, or null when it would not.
     */
    public function wouldOverAssign(User $user, string $currency, Money $delta): ?Money
    {
        if (! $delta->isPositive()) {
            return null;
        }

        $after = $this->moneyMap->readyToAssign($user, strtoupper($currency))->subtract($delta);

        return $after->isNegative() ? $after->abs() : null;
    }
}
