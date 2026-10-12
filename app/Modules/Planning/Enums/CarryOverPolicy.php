<?php

namespace App\Modules\Planning\Enums;

/**
 * What happens to a bucket's unspent money when its cycle ends.
 */
enum CarryOverPolicy: string
{
    case Carry = 'carry';
    case Release = 'release';
    case Overflow = 'overflow';

    public function label(): string
    {
        return __('enums.carry_over.'.$this->value);
    }
}
