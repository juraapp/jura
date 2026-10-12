<?php

namespace App\Modules\Ledger\Enums;

/**
 * How often an account's accrued interest is added to its balance (and so starts earning interest itself).
 */
enum YieldCompounding: string
{
    case Daily = 'daily';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return __('enums.yield_compounding.'.$this->value);
    }
}
