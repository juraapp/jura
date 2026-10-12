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

    public function label(): string
    {
        return __('enums.allocation_source.'.$this->value);
    }
}
