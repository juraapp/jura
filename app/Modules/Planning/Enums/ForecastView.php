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

    /**
     * The view every forecast uses unless the user picks another: confirmed and expected income.
     */
    public static function default(): self
    {
        return self::Expected;
    }

    /**
     * The view named in a request, or the default when it is missing or unknown.
     */
    public static function fromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::default();
    }

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
