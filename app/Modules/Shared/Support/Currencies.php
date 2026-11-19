<?php

namespace App\Modules\Shared\Support;

/**
 * The currencies Jura offers in its pickers (ISO 4217 codes). Keep in step with resources/js/currencies.js. A
 * currency outside this list still works wherever an account already uses it.
 */
final class Currencies
{
    /** @var array<string, string> code => English name */
    public const NAMES = [
        'COP' => 'Colombian peso',
        'USD' => 'US dollar',
        'EUR' => 'Euro',
        'MXN' => 'Mexican peso',
        'ARS' => 'Argentine peso',
        'BRL' => 'Brazilian real',
        'CLP' => 'Chilean peso',
        'PEN' => 'Peruvian sol',
        'UYU' => 'Uruguayan peso',
        'PYG' => 'Paraguayan guaraní',
        'BOB' => 'Bolivian boliviano',
        'VES' => 'Venezuelan bolívar',
        'CRC' => 'Costa Rican colón',
        'GTQ' => 'Guatemalan quetzal',
        'HNL' => 'Honduran lempira',
        'NIO' => 'Nicaraguan córdoba',
        'PAB' => 'Panamanian balboa',
        'DOP' => 'Dominican peso',
        'CAD' => 'Canadian dollar',
        'GBP' => 'British pound',
        'CHF' => 'Swiss franc',
        'JPY' => 'Japanese yen',
        'CNY' => 'Chinese yuan',
        'AUD' => 'Australian dollar',
        'NZD' => 'New Zealand dollar',
        'SEK' => 'Swedish krona',
        'NOK' => 'Norwegian krone',
        'DKK' => 'Danish krone',
        'PLN' => 'Polish złoty',
        'INR' => 'Indian rupee',
    ];

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::NAMES);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (string $code, string $name) => ['value' => $code, 'label' => "{$code} — {$name}"], array_keys(self::NAMES), self::NAMES);
    }
}
