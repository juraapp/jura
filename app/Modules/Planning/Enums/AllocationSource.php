<?php

namespace App\Modules\Planning\Enums;

enum AllocationSource: string
{
    case Income = 'income';
    case Planned = 'planned';
    case Transfer = 'transfer';
    case Carry = 'carry';
    case Spend = 'spend';
    case Adjustment = 'adjustment';
    case Opening = 'opening';

    /** Money exchanged with ready to assign: a single row with no counterpart bucket. */
    case Ready = 'ready';

    public function label(): string
    {
        return __('enums.allocation_source.'.$this->value);
    }
}
