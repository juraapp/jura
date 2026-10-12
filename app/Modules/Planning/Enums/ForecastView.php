<?php

namespace App\Modules\Planning\Enums;

/**
 * Which planned income a forecast trusts. Expenses always count in full.
 */
enum ForecastView: string
{
    case Conservative = 'conservative';
    case Expected = 'expected';
    case Optimistic = 'optimistic';

    public function label(): string
    {
        return __('enums.forecast_view.'.$this->value);
    }

    public function includesIncome(Certainty $certainty): bool
    {
        return match ($this) {
            self::Conservative => $certainty === Certainty::Confirmed,
            self::Expected => $certainty !== Certainty::Speculative,
            self::Optimistic => true,
        };
    }
}
