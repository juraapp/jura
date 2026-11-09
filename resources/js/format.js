// Display formatting driven by the user's preferences (Settings -> Preferences).
// Pure functions with no framework imports so they can be tested with `node --test`.
// Keep the rules in step with app/Modules/Shared/Support/UserFormat.php.

const NARROW_ONLY = { display: 'narrowSymbol' };

const localeTag = (locale) => (locale || 'en').replace('_', '-');

const isAuto = (value) => value === undefined || value === null || value === 'auto' || value === '';

const pad = (value) => String(value).padStart(2, '0');

function toDate(value) {
    if (value instanceof Date) return value;
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) return new Date(`${value}T00:00:00`);
    if (typeof value === 'string' && /^\d{4}-\d{2}$/.test(value)) return new Date(`${value}-01T00:00:00`);

    return new Date(value);
}

function toTimeParts(value) {
    if (value instanceof Date) return { hours: value.getHours(), minutes: value.getMinutes() };
    const match = /^(\d{1,2}):(\d{2})/.exec(String(value));
    if (match) return { hours: Number(match[1]), minutes: Number(match[2]) };
    const date = new Date(value);

    return { hours: date.getHours(), minutes: date.getMinutes() };
}

function localeSeparators(locale) {
    const parts = new Intl.NumberFormat(localeTag(locale)).formatToParts(1234567.5);

    return {
        decimal: parts.find((part) => part.type === 'decimal')?.value ?? '.',
        group: parts.find((part) => part.type === 'group')?.value ?? ',',
    };
}

/**
 * Reads an amount typed by the user into a plain decimal string with two decimals ("1234.50"), the shape the server
 * expects. Accepts either separator: with both, the last one is the decimal separator; a lone separator followed by
 * one or two digits is decimal, one repeated or followed by exactly three digits is a thousands separator unless it is
 * the user's decimal separator (so "1.234" is 1234 for a comma-decimal user and 1.234 is invalid for a dot-decimal one
 * only when it has three decimals). Currency symbols, a three-letter currency code before or after the amount, spaces
 * and apostrophes are ignored; a minus (also U+2212) before the digits or parentheses make it negative. Any other
 * letter, a minus inside the number or thousands groups that are not three digits make it unreadable. Returns '' for a
 * blank input and null when it cannot be read.
 *
 * @param {string|number|null|undefined} text
 * @param {{ decimal?: string }} separators  the user's decimal separator ('.' or ',')
 * @returns {string|null}
 */
export function parseMoneyInput(text, { decimal = '.' } = {}) {
    if (text === null || text === undefined) return '';
    if (typeof text === 'number') return Number.isFinite(text) ? text.toFixed(2) : null;

    const trimmed = String(text).trim();
    if (trimmed === '') return '';

    const body = trimmed
        .replace(/\u2212/g, '-')
        .replace(/^[A-Za-z]{3}(?![A-Za-z])/, '')
        .replace(/(?<![A-Za-z])[A-Za-z]{3}$/, '')
        .replace(/[\p{Sc}\s']/gu, '');
    const parts = body.match(/^(-)?(\()?(-)?([\d.,]+)(\))?$/);
    if (parts === null || Boolean(parts[2]) !== Boolean(parts[5]) || (parts[1] && parts[3])) return null;

    const negative = Boolean(parts[1] || parts[2] || parts[3]);
    const cleaned = parts[4];
    if (!/\d/.test(cleaned)) return null;

    const lastDot = cleaned.lastIndexOf('.');
    const lastComma = cleaned.lastIndexOf(',');
    let decimalMark = null;

    if (lastDot !== -1 && lastComma !== -1) {
        decimalMark = lastDot > lastComma ? '.' : ',';
    } else if (lastDot !== -1 || lastComma !== -1) {
        const mark = lastDot !== -1 ? '.' : ',';
        const occurrences = cleaned.split(mark).length - 1;
        const digitsAfter = cleaned.length - cleaned.lastIndexOf(mark) - 1;

        if (occurrences === 1 && digitsAfter !== 3) {
            decimalMark = mark;
        } else if (occurrences === 1 && digitsAfter === 3) {
            decimalMark = mark === decimal ? mark : null;
        }
    }

    let whole = cleaned;
    let fraction = '';
    if (decimalMark !== null) {
        const index = cleaned.lastIndexOf(decimalMark);
        whole = cleaned.slice(0, index);
        fraction = cleaned.slice(index + 1);
    }

    if (decimalMark !== null && whole.includes(decimalMark)) return null;
    if (/[.,]/.test(whole) && !/^\d{1,3}([.,])\d{3}(\1\d{3})*$/.test(whole)) return null;
    whole = whole.replace(/[.,]/g, '');
    if (fraction.length > 2 || /[.,]/.test(fraction)) return null;
    if (whole === '' && fraction === '') return null;

    const integer = (whole.replace(/^0+(?=\d)/, '') || '0');
    const value = `${integer}.${fraction.padEnd(2, '0')}`;

    return negative && /[1-9]/.test(value) ? `-${value}` : value;
}

/**
 * Shows an amount in a money field with the user's separators: grouped, and with decimals only when it has cents.
 *
 * @param {string|number|null|undefined} value  a plain decimal ("1234.5")
 * @param {{ decimal?: string, group?: string }} separators
 * @returns {string}
 */
export function formatMoneyInput(value, { decimal = '.', group = ',' } = {}) {
    if (value === null || value === undefined || value === '') return '';

    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return String(value);

    const [whole, fraction] = Math.abs(numeric).toFixed(2).split('.');
    const grouped = group === '' ? whole : whole.replace(/\B(?=(\d{3})+(?!\d))/g, group);
    const sign = numeric < 0 && Number(Math.abs(numeric).toFixed(2)) !== 0 ? '-' : '';

    return sign + grouped + (fraction === '00' ? '' : decimal + fraction);
}

export function createFormat(preferences = {}, locale = 'en') {
    const prefs = preferences ?? {};
    const language = localeTag(locale).slice(0, 2);
    const defaults = localeSeparators(locale);

    const decimal = isAuto(prefs.decimal_separator) ? defaults.decimal : prefs.decimal_separator;
    const group = isAuto(prefs.thousands_separator) ? defaults.group : prefs.thousands_separator === 'none' ? '' : prefs.thousands_separator;
    const configuredDigits = prefs.fraction_digits === null || prefs.fraction_digits === undefined || prefs.fraction_digits === '' ? null : Number(prefs.fraction_digits);

    const number = (value, fractionDigits = null) => {
        const digits = fractionDigits ?? configuredDigits ?? 2;
        const numeric = Number(value);
        const fixed = Math.abs(numeric).toFixed(digits);
        const [whole, fraction] = fixed.split('.');
        const grouped = group === '' ? whole : whole.replace(/\B(?=(\d{3})+(?!\d))/g, group);
        const negative = numeric < 0 && Number(fixed) !== 0 ? '-' : '';

        return negative + grouped + (fraction === undefined ? '' : decimal + fraction);
    };

    const date = (value, { year = true } = {}) => {
        const d = toDate(value);
        if (Number.isNaN(d.getTime())) return '';
        const y = d.getFullYear();
        const m = pad(d.getMonth() + 1);
        const day = pad(d.getDate());

        switch (isAuto(prefs.date_format) ? 'auto' : prefs.date_format) {
            case 'iso':
                return year ? `${y}-${m}-${day}` : `${m}-${day}`;
            case 'mdy':
                return year ? `${m}/${day}/${y}` : `${m}/${day}`;
            case 'dmy':
                return year ? `${day}/${m}/${y}` : `${day}/${m}`;
            case 'medium': {
                const month = d.toLocaleDateString(localeTag(locale), { month: 'short' });

                return `${d.getDate()} ${month}${year ? ` ${y}` : ''}`;
            }
            default:
                return d.toLocaleDateString(localeTag(locale), { day: 'numeric', month: 'short', ...(year ? { year: 'numeric' } : {}) });
        }
    };

    // "March 2026" (or "Mar 26" when short) in the user's language.
    const month = (value, { short = false } = {}) => toDate(value).toLocaleDateString(
        localeTag(locale),
        short ? { month: 'short', year: '2-digit' } : { month: 'long', year: 'numeric' },
    );

    const time = (value) => {
        const { hours, minutes } = toTimeParts(value);
        const twelveHour = prefs.time_format === '12h' || (isAuto(prefs.time_format) && language === 'en');

        if (!twelveHour) return `${pad(hours)}:${pad(minutes)}`;

        return `${hours % 12 === 0 ? 12 : hours % 12}:${pad(minutes)} ${hours < 12 ? 'AM' : 'PM'}`;
    };

    const dateTime = (value, timeValue = null) => {
        const t = timeValue ?? (value instanceof Date ? value : null);

        return t === null ? date(value) : `${date(value)} ${time(t)}`;
    };

    const money = (value, currency = 'COP', { fractionDigits = null, signed = false } = {}) => {
        const numeric = Number(value);
        const display = isAuto(prefs.currency_display) ? 'auto' : prefs.currency_display;
        const probe = new Intl.NumberFormat(localeTag(locale), {
            style: 'currency',
            currency,
            currencyDisplay: display === 'code' ? 'code' : display === 'symbol' ? NARROW_ONLY.display : 'symbol',
        });
        const digits = fractionDigits ?? configuredDigits ?? probe.resolvedOptions().maximumFractionDigits;
        const body = number(Math.abs(numeric), digits);
        const sign = numeric < 0 && Number(Math.abs(numeric).toFixed(digits)) !== 0 ? '-' : signed && numeric > 0 ? '+' : '';

        if (display === 'none') return sign + body;

        const parts = probe.formatToParts(1);
        const symbolIndex = parts.findIndex((part) => part.type === 'currency');
        const integerIndex = parts.findIndex((part) => part.type === 'integer');
        const symbol = parts[symbolIndex]?.value ?? currency;

        let before = symbolIndex < integerIndex;
        let spaced = parts.some((part) => part.type === 'literal' && /^[\s ]+$/.test(part.value));

        const position = isAuto(prefs.currency_position) ? 'auto' : prefs.currency_position;
        if (position !== 'auto') {
            before = position.startsWith('before');
            spaced = position.endsWith('_space');
        }

        const gap = spaced ? ' ' : '';

        return sign + (before ? symbol + gap + body : body + gap + symbol);
    };

    const separators = { decimal, group };
    const parseMoney = (text) => parseMoneyInput(text, separators);
    const formatMoney = (value) => formatMoneyInput(value, separators);

    return { number, date, month, time, dateTime, money, separators, parseMoney, formatMoney };
}
