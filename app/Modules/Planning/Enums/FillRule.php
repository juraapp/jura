<?php

namespace App\Modules\Planning\Enums;

/**
 * How a bucket claims its share of an income in the priority waterfall.
 */
enum FillRule: string
{
    case Fixed = 'fixed';
    case Percent = 'percent';
    case FillToTarget = 'fill_to_target';

    public function label(): string
    {
        return __('enums.fill_rule.'.$this->value);
    }
}
