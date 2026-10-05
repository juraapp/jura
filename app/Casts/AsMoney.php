<?php

namespace App\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<Money, Money|string|int|float>
 */
class AsMoney implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return is_null($value) ? null : Money::of($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        return $value instanceof Money ? $value->toDecimalString() : Money::of($value)->toDecimalString();
    }
}
