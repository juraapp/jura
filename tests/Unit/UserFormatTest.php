<?php

namespace Tests\Unit;

use App\Modules\Shared\Support\Money;
use App\Modules\Shared\Support\UserFormat;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserFormatTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function dates(): array
    {
        return [
            'iso' => ['iso', '2026-03-09'],
            'month first' => ['mdy', '03/09/2026'],
            'day first' => ['dmy', '09/03/2026'],
            'medium' => ['medium', '9 Mar 2026'],
            'auto english' => ['auto', 'Mar 9, 2026'],
        ];
    }

    #[DataProvider('dates')]
    public function test_dates_follow_the_chosen_style(string $style, string $expected): void
    {
        $format = new UserFormat(['date_format' => $style], 'en');

        $this->assertSame($expected, $format->date(Carbon::parse('2026-03-09')));
    }

    public function test_dates_without_a_year(): void
    {
        $this->assertSame('03/09', (new UserFormat(['date_format' => 'mdy']))->date(Carbon::parse('2026-03-09'), false));
        $this->assertSame('09/03', (new UserFormat(['date_format' => 'dmy']))->date(Carbon::parse('2026-03-09'), false));
    }

    public function test_time_is_12_or_24_hour_and_auto_follows_the_language(): void
    {
        $time = Carbon::parse('2026-03-09 15:05:00');

        $this->assertSame('3:05 PM', (new UserFormat(['time_format' => '12h'], 'es'))->time($time));
        $this->assertSame('15:05', (new UserFormat(['time_format' => '24h'], 'en'))->time($time));
        $this->assertSame('3:05 PM', (new UserFormat([], 'en'))->time($time));
        $this->assertSame('15:05', (new UserFormat([], 'es'))->time($time));
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function numbers(): array
    {
        return [
            'us style' => [['decimal_separator' => '.', 'thousands_separator' => ',', 'fraction_digits' => 2], '1,234,567.89'],
            'european style' => [['decimal_separator' => ',', 'thousands_separator' => '.', 'fraction_digits' => 1], '1.234.567,9'],
            'spaces' => [['decimal_separator' => ',', 'thousands_separator' => ' ', 'fraction_digits' => 0], '1 234 568'],
            'no grouping' => [['decimal_separator' => '.', 'thousands_separator' => 'none', 'fraction_digits' => 2], '1234567.89'],
        ];
    }

    /**
     * @param  array<string, mixed>  $preferences
     */
    #[DataProvider('numbers')]
    public function test_numbers_use_the_chosen_separators(array $preferences, string $expected): void
    {
        $this->assertSame($expected, (new UserFormat($preferences))->number(1234567.891));
    }

    public function test_negative_numbers_keep_their_sign_and_tiny_negatives_do_not_show_minus_zero(): void
    {
        $format = new UserFormat(['decimal_separator' => '.', 'thousands_separator' => ',', 'fraction_digits' => 0]);

        $this->assertSame('-1,500', $format->number(-1500));
        $this->assertSame('0', $format->number(-0.2));
    }

    public function test_money_with_a_symbol_code_or_nothing_and_a_chosen_position(): void
    {
        $base = ['decimal_separator' => '.', 'thousands_separator' => ',', 'fraction_digits' => 2];
        $amount = Money::of('1234567.5');

        $this->assertSame('$1,234,567.50', (new UserFormat($base + ['currency_display' => 'symbol', 'currency_position' => 'before']))->money($amount, 'COP'));
        $this->assertSame('$ 1,234,567.50', (new UserFormat($base + ['currency_display' => 'symbol', 'currency_position' => 'before_space']))->money($amount, 'COP'));
        $this->assertSame('COP 1,234,567.50', (new UserFormat($base + ['currency_display' => 'code', 'currency_position' => 'before_space']))->money($amount, 'COP'));
        $this->assertSame('1,234,567.50 COP', (new UserFormat($base + ['currency_display' => 'code', 'currency_position' => 'after_space']))->money($amount, 'COP'));
        $this->assertSame('1,234,567.50', (new UserFormat($base + ['currency_display' => 'none']))->money($amount, 'COP'));
        $this->assertSame('-$1,234,567.50', (new UserFormat($base + ['currency_display' => 'symbol', 'currency_position' => 'before']))->money($amount->negate(), 'COP'));
    }

    public function test_the_european_money_example_from_the_issue(): void
    {
        $format = new UserFormat(['decimal_separator' => ',', 'thousands_separator' => '.', 'fraction_digits' => 1, 'currency_display' => 'symbol', 'currency_position' => 'before_space']);

        $this->assertSame('$ 123.456.789,0', $format->money('123456789', 'COP'));
    }

    public function test_auto_follows_the_language(): void
    {
        $this->assertSame('$1,234.50', (new UserFormat([], 'en'))->money('1234.5', 'USD'));
        $this->assertSame('1.234,50', (new UserFormat([], 'es'))->number(1234.5));
    }
}
