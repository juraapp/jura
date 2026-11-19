<?php

namespace Tests\Unit;

use App\Modules\Shared\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{string|int|float, string}>
     */
    public static function amounts(): array
    {
        return [
            'two decimals stay' => ['10.99', '10.99'],
            'integer' => [1500, '1500.00'],
            'half rounds up' => ['10.995', '11.00'],
            'below half rounds down' => ['10.994', '10.99'],
            'negative half rounds away from zero' => ['-10.995', '-11.00'],
            'tiny float has no exponent' => [0.00001, '0.00'],
            'float drift is rounded' => [0.1 + 0.2, '0.30'],
        ];
    }

    #[DataProvider('amounts')]
    public function test_of_rounds_to_cents_half_away_from_zero(string|int|float $input, string $expected): void
    {
        $this->assertSame($expected, Money::of($input)->toDecimalString());
    }

    public function test_of_rejects_exponent_notation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of('1e3');
    }
}
