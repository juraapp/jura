import ApexCharts from 'apexcharts';

// Palette from the dataviz skill's validated reference (references/palette.md):
// fixed categorical hue order, CVD-safe in both light and dark, plus the
// income/expense/savings convention (green/red/blue) used across badges.
const PALETTE = {
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

function isDark() {
    return document.documentElement.classList.contains('dark');
}

window.isDarkNow = isDark;

window.formatMoney = function formatMoney(value, currency, locale) {
    return new Intl.NumberFormat((locale || 'en').replace('_', '-'), {
        style: 'currency',
        currency: currency || 'COP',
        maximumFractionDigits: 0,
    }).format(value);
};

window.formatCompact = function formatCompact(value, locale) {
    return new Intl.NumberFormat((locale || 'en').replace('_', '-'), { notation: 'compact', maximumFractionDigits: 1 }).format(value);
};

window.financeCharts = window.financeCharts || {};

/**
 * Renders (or re-renders) a chart into #id using an options factory so it can
 * be redrawn with the correct palette whenever the user toggles dark mode.
 */
export function renderFinanceChart(id, optionsFactory) {
    const el = document.getElementById(id);
    if (! el) return;

    if (window.financeCharts[id]) {
        window.financeCharts[id].chart.destroy();
    }

    const mode = isDark() ? 'dark' : 'light';
    const options = optionsFactory(PALETTE[mode], mode);
    const chart = new ApexCharts(el, options);
    chart.render();

    window.financeCharts[id] = { chart, optionsFactory };
}

window.renderFinanceChart = renderFinanceChart;

window.addEventListener('theme-changed', () => {
    Object.entries(window.financeCharts).forEach(([id]) => {
        renderFinanceChart(id, window.financeCharts[id].optionsFactory);
    });
});
