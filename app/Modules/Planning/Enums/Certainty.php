<?php

namespace App\Modules\Planning\Enums;

/**
 * How sure the user is that a planned amount will really happen.
 */
enum Certainty: string
{
    case Confirmed = 'confirmed';
    case Expected = 'expected';
    case Speculative = 'speculative';

    public function label(): string
    {
        return __('enums.certainty.'.$this->value);
    }
}
