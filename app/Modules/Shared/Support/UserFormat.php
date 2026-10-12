<?php

namespace App\Modules\Shared\Support;

use Carbon\CarbonInterface;
use NumberFormatter;

/**
 * Formats dates, times, numbers and money the way a user asked to see them
 * (Settings -> Preferences). The same rules live in resources/js/format.js for
 * the browser; keep both in step. Any option left on "auto"/null follows the language.
 */
final class UserFormat
{
    public const DATE_FORMATS = ['auto', 'iso', 'mdy', 'dmy', 'medium'];

    public const TIME_FORMATS = ['auto', '12h', '24h'];

    public const DECIMAL_SEPARATORS = ['.', ','];

    public const THOUSANDS_SEPARATORS = [',', '.', ' ', 'none'];

    public const CURRENCY_DISPLAYS = ['auto', 'symbol', 'code', 'none'];

    private const NARROW_SYMBOLS = [
        'COP' => '$', 'USD' => '$', 'MXN' => '$', 'ARS' => '$', 'CLP' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥', 'PEN' => 'S/',
    ];

    public const CURRENCY_POSITIONS = ['auto', 'before', 'before_space', 'after', 'after_space'];

    /**
     * @param  array<string, mixed>  $preferences
     */
    public function __construct(private readonly array $preferences = [], private readonly string $locale = 'en') {}

    public function date(CarbonInterface $date, bool $withYear = true): string
    {
        $english = $this->language() === 'en';

        return match ($this->preference('date_format', 'auto')) {
            'iso' => $date->format($withYear ? 'Y-m-d' : 'm-d'),
            'mdy' => $date->format($withYear ? 'm/d/Y' : 'm/d'),
            'dmy' => $date->format($withYear ? 'd/m/Y' : 'd/m'),
            'medium' => $date->locale($this->language())->translatedFormat($withYear ? 'j M Y' : 'j M'),
            default => $date->locale($this->language())->translatedFormat($english ? ($withYear ? 'M j, Y' : 'M j') : ($withYear ? 'j M Y' : 'j M')),
        };
    }

    public function time(CarbonInterface $time): string
    {
        $twelveHour = match ($this->preference('time_format', 'auto')) {
            '12h' => true,
            '24h' => false,
            default => $this->language() === 'en',
        };

        return $time->format($twelveHour ? 'g:i A' : 'H:i');
    }

    public function number(float|int|string $value, ?int $fractionDigits = null): string
    {
        $digits = $fractionDigits ?? $this->preference('fraction_digits');
        $digits = $digits === null ? 2 : (int) $digits;

        $formatted = number_format((float) $value, $digits, '.', '');
        [$whole, $fraction] = array_pad(explode('.', ltrim($formatted, '-')), 2, null);

        $thousands = $this->thousandsSeparator();
        $whole = $thousands === '' ? $whole : preg_replace('/\B(?=(\d{3})+(?!\d))/', $thousands, $whole);

        $negative = str_starts_with($formatted, '-') && (float) $formatted !== 0.0 ? '-' : '';

        return $negative.$whole.($fraction === null ? '' : $this->decimalSeparator().$fraction);
    }

    public function money(Money|float|int|string $amount, string $currency): string
    {
        $value = $amount instanceof Money ? $amount->toFloat() : (float) $amount;
        $formatter = new NumberFormatter($this->intlLocale(), NumberFormatter::CURRENCY);

        $digits = $this->preference('fraction_digits');
        $digits = $digits === null ? (int) $formatter->getAttribute(NumberFormatter::MAX_FRACTION_DIGITS) : (int) $digits;
        $number = $this->number(abs($value), $digits);
        $sign = $value < 0 && round(abs($value), $digits) > 0 ? '-' : '';

        $display = $this->preference('currency_display', 'auto');
        if ($display === 'none') {
            return $sign.$number;
        }

        $symbol = $display === 'code' ? $currency : $this->symbol($formatter, $currency, $display === 'symbol');
        $pattern = $formatter->getPattern();
        $position = $this->preference('currency_position', 'auto');

        if ($position === 'auto') {
            $before = str_starts_with($pattern, '¤');
            $spaced = (bool) preg_match('/¤[\x{00A0} ]|[\x{00A0} ]¤/u', $pattern) || ($display === 'code' && ! $this->isSymbolic($symbol));
        } else {
            $before = str_starts_with($position, 'before');
            $spaced = str_ends_with($position, '_space');
        }

        $gap = $spaced ? ' ' : '';

        return $sign.($before ? $symbol.$gap.$number : $number.$gap.$symbol);
    }

    /**
     * "symbol" always shows the short symbol people expect ($ for any peso or dollar);
     * "auto" shows whatever the language uses for that currency.
     */
    private function symbol(NumberFormatter $formatter, string $currency, bool $narrow): string
    {
        if ($narrow && isset(self::NARROW_SYMBOLS[$currency])) {
            return self::NARROW_SYMBOLS[$currency];
        }

        $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currency);

        return $formatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL);
    }

    private function isSymbolic(string $symbol): bool
    {
        return ! preg_match('/^[A-Z]{3}$/', $symbol);
    }

    private function decimalSeparator(): string
    {
        return $this->preference('decimal_separator') ?? (new NumberFormatter($this->intlLocale(), NumberFormatter::DECIMAL))->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
    }

    private function thousandsSeparator(): string
    {
        $configured = $this->preference('thousands_separator');

        return match ($configured) {
            null => (new NumberFormatter($this->intlLocale(), NumberFormatter::DECIMAL))->getSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL),
            'none' => '',
            default => $configured,
        };
    }

    private function preference(string $key, mixed $default = null): mixed
    {
        $value = $this->preferences[$key] ?? null;

        return $value === null || $value === 'auto' ? $default : $value;
    }

    private function language(): string
    {
        return substr($this->locale, 0, 2);
    }

    private function intlLocale(): string
    {
        return str_replace('_', '-', $this->locale);
    }
}
