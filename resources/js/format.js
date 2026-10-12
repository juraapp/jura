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

    return { number, date, month, time, dateTime, money };
}
