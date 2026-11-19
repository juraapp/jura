// The currencies Jura offers in its pickers (ISO 4217). Keep in step with app/Modules/Shared/Support/Currencies.php.
// Pure: no framework imports, so it can be tested with `node --test`.

export const CURRENCY_CODES = [
    'COP', 'USD', 'EUR', 'MXN', 'ARS', 'BRL', 'CLP', 'PEN', 'UYU', 'PYG',
    'BOB', 'VES', 'CRC', 'GTQ', 'HNL', 'NIO', 'PAB', 'DOP', 'CAD', 'GBP',
    'CHF', 'JPY', 'CNY', 'AUD', 'NZD', 'SEK', 'NOK', 'DKK', 'PLN', 'INR',
];

/**
 * The codes a currency picker shows: the user's default first, then the currencies their accounts use, then the rest
 * of the list, without duplicates. With `restrictTo`, only those codes (in the same order).
 *
 * @param {{ defaultCode?: string|null, extra?: string[], restrictTo?: string[]|null, current?: string|null }} options
 * @returns {string[]}
 */
export function currencyCodes({ defaultCode = null, extra = [], restrictTo = null, current = null } = {}) {
    const ordered = [defaultCode, ...extra, ...CURRENCY_CODES, current]
        .filter(Boolean)
        .map((code) => String(code).toUpperCase());
    const unique = [...new Set(ordered)];

    if (!restrictTo) return unique;

    const allowed = new Set(restrictTo.map((code) => String(code).toUpperCase()));

    return [...new Set([...unique.filter((code) => allowed.has(code)), ...allowed])];
}

/**
 * "COP — Colombian peso" in the user's language (the code alone when the browser does not know the name).
 */
export function currencyLabel(code, locale = 'en') {
    try {
        const name = new Intl.DisplayNames([locale.replace('_', '-')], { type: 'currency' }).of(code);

        return name && name !== code ? `${code} — ${name}` : code;
    } catch {
        return code;
    }
}
