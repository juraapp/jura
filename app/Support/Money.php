<?php

namespace App\Support;

use InvalidArgumentException;
use Stringable;

/**
 * Immutable money value object backed by bcmath so amounts are never
 * manipulated as PHP floats (which would introduce rounding drift on
 * financial totals). Internally stores a fixed-scale decimal string.
 */
final class Money implements Stringable
{
    private const SCALE = 2;

    private readonly string $amount;

    private function __construct(string $amount)
    {
        $this->amount = bcadd($amount, '0', self::SCALE);
    }

    public static function of(string|int|float $amount): self
    {
        return new self((string) $amount);
    }

    public static function zero(): self
    {
        return new self('0');
    }

    public function add(self $other): self
    {
        return new self(bcadd($this->amount, $other->amount, self::SCALE));
    }

    public function subtract(self $other): self
    {
        return new self(bcsub($this->amount, $other->amount, self::SCALE));
    }

    public function multiply(string|int|float $factor): self
    {
        return new self(bcmul($this->amount, (string) $factor, self::SCALE));
    }

    public function divide(string|int|float $divisor): self
    {
        if (bccomp((string) $divisor, '0', self::SCALE) === 0) {
            throw new InvalidArgumentException('Cannot divide an amount by zero.');
        }

        return new self(bcdiv($this->amount, (string) $divisor, self::SCALE));
    }

    public function isNegative(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) < 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) > 0;
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) === 0;
    }

    public function abs(): self
    {
        return $this->isNegative() ? new self(bcmul($this->amount, '-1', self::SCALE)) : $this;
    }

    public function negate(): self
    {
        return new self(bcmul($this->amount, '-1', self::SCALE));
    }

    public function greaterThan(self $other): bool
    {
        return bccomp($this->amount, $other->amount, self::SCALE) > 0;
    }

    public function lessThan(self $other): bool
    {
        return bccomp($this->amount, $other->amount, self::SCALE) < 0;
    }

    public function equals(self $other): bool
    {
        return bccomp($this->amount, $other->amount, self::SCALE) === 0;
    }

    /**
     * Percentage this amount represents of $whole, e.g. gasto / ingreso * 100.
     * Returns 0.0 when $whole is zero to keep callers free of division guards.
     */
    public function percentageOf(self $whole): float
    {
        if ($whole->isZero()) {
            return 0.0;
        }

        return (float) bcmul(bcdiv($this->amount, $whole->amount, 6), '100', 2);
    }

    public function toFloat(): float
    {
        return (float) $this->amount;
    }

    /**
     * Raw decimal string, suitable for persisting to a DECIMAL(15,2) column.
     */
    public function toDecimalString(): string
    {
        return $this->amount;
    }

    public function format(string $currency = 'COP', string $locale = 'es'): string
    {
        $formatter = new \NumberFormatter(str_replace('_', '-', $locale), \NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($this->toFloat(), $currency);
    }

    public function __toString(): string
    {
        return $this->amount;
    }
}
