// Palette from the dataviz skill's validated reference (references/palette.md):
// fixed categorical hue order, CVD-safe in both light and dark, plus the
// income/expense/savings convention (green/red/blue) used across badges.
export const CHART_PALETTE = {
    light: {
        text: '#52514e',
        grid: '#e5e7eb',
        series: ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'],
        income: '#008300',
        expense: '#e34948',
        savings: '#2a78d6',
    },
    dark: {
        text: '#c3c2b7',
        grid: '#374151',
        series: ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300', '#9085e9', '#e66767'],
        income: '#22c55e',
        expense: '#e66767',
        savings: '#3987e5',
    },
};

export function formatMoney(value, currency, locale) {
    return new Intl.NumberFormat((locale || 'en').replace('_', '-'), {
        style: 'currency',
        currency: currency || 'COP',
        maximumFractionDigits: 0,
    }).format(value);
}

export function formatCompact(value, locale) {
    return new Intl.NumberFormat((locale || 'en').replace('_', '-'), {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);
}
