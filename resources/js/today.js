/**
 * The calendar date (YYYY-MM-DD) it is right now in a timezone. Never use
 * `new Date().toISOString()` for a default date: that is the UTC date, which is
 * already tomorrow during the evening west of Greenwich.
 *
 * @param {string|null|undefined} timeZone IANA name, e.g. "America/Bogota"; the browser's own when missing or unknown
 * @param {Date} [now]
 * @returns {string}
 */
export function localDate(timeZone, now = new Date()) {
    let parts;

    try {
        parts = new Intl.DateTimeFormat('en-US', {
            timeZone: timeZone || undefined,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).formatToParts(now);
    } catch {
        return localDate(undefined, now);
    }

    const part = (type) => parts.find((p) => p.type === type).value;

    return `${part('year')}-${part('month')}-${part('day')}`;
}
