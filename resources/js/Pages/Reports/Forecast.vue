<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Money from '@/Components/Money.vue';
import { useDarkMode } from '@/composables/useDarkMode';
import { CHART_PALETTE, formatCompact, formatMoney } from '@/chartPalette';
import { index as plannedEventsIndex } from '@/routes/planned-events';

const props = defineProps({
    currencies: { type: Array, required: true },
    views: { type: Array, required: true },
});

const page = usePage();
const { isDark } = useDarkMode();
const locale = () => page.props.auth.user?.locale ?? 'en';
const palette = () => CHART_PALETTE[isDark.value ? 'dark' : 'light'];

const viewColors = () => ({ conservative: palette().series[1], expected: palette().series[2], optimistic: palette().series[3] });

const labelsFor = (currency) => [...currency.actual.map((row) => row.label), ...currency.projected.expected.map((row) => row.label)];

const seriesFor = (currency) => {
    const pastCount = currency.actual.length;
    const futureCount = currency.projected.expected.length;
    const blanks = (n) => Array(n).fill(null);
    const lastActual = currency.actual.length ? currency.actual[currency.actual.length - 1].netWorth : currency.opening;

    return [
        { name: 'Recorded', data: [...currency.actual.map((row) => row.netWorth), ...blanks(futureCount)] },
        ...props.views.map((view) => ({
            name: `Projected, ${view.label.toLowerCase()}`,
            // Start from the latest recorded value so each dashed line visibly continues the solid one.
            data: [...blanks(Math.max(pastCount - 1, 0)), lastActual, ...currency.projected[view.value].map((row) => row.closing)],
        })),
    ];
};

const optionsFor = (currency) => ({
    chart: { type: 'line', height: 340, fontFamily: 'Figtree, sans-serif', toolbar: { show: false } },
    colors: [palette().series[0], viewColors().conservative, viewColors().expected, viewColors().optimistic],
    stroke: { width: [3, 2, 2, 2], dashArray: [0, 6, 6, 6], curve: 'smooth' },
    xaxis: { categories: labelsFor(currency), labels: { style: { colors: palette().text } } },
    yaxis: { labels: { style: { colors: palette().text }, formatter: (v) => formatCompact(v, locale()) } },
    grid: { borderColor: palette().grid },
    legend: { labels: { colors: Array(4).fill(palette().text), useSeriesColors: false } },
    dataLabels: { enabled: false },
    annotations: currency.actual.length ? { xaxis: [{ x: currency.actual[currency.actual.length - 1].label, borderColor: palette().text, strokeDashArray: 2, label: { text: 'Today', style: { color: '#fff', background: '#6b7280' } } }] } : {},
    tooltip: { y: { formatter: (v) => (v === null ? '' : formatMoney(v, currency.currency, locale())) } },
});

const rowsFor = (currency) => currency.projected.expected.map((row, index) => ({
    label: row.label,
    income: row.income,
    expense: row.expense,
    conservative: currency.projected.conservative[index].closing,
    expected: row.closing,
    optimistic: currency.projected.optimistic[index].closing,
}));
</script>

<template>
    <Head title="Forecast report" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Forecast</h2>
        </template>

        <div class="mx-auto max-w-6xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
            <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                The <strong>solid line</strong> is your real net worth, month by month, from recorded transactions. The <strong>dashed lines</strong> are estimates
                built from the income and expenses you planned in
                <Link :href="plannedEventsIndex.url()" class="font-medium text-primary-600 hover:underline dark:text-primary-400">Cash flow</Link>,
                under three levels of trust in your planned income.
            </p>

            <EmptyState
                v-if="currencies.length === 0"
                icon="document-chart-bar"
                title="Nothing to forecast yet"
                description="Create an account and plan some income or expenses in Cash flow to see your projected balance here."
            />

            <section v-for="currency in currencies" :key="currency.currency" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Net worth, {{ currency.currency }}</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Today: <Money :amount="currency.opening" :currency="currency.currency" /></p>
                </div>

                <apexchart
                    :key="`fc-${currency.currency}-${isDark}`"
                    type="line"
                    height="340"
                    :options="optionsFor(currency)"
                    :series="seriesFor(currency)"
                />

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-4 font-semibold">Month (projected)</th>
                                <th class="px-4 py-2 text-right font-semibold">Planned income</th>
                                <th class="px-4 py-2 text-right font-semibold">Planned expenses</th>
                                <th v-for="view in views" :key="view.value" class="px-4 py-2 text-right font-semibold">{{ view.label }} balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 tabular-nums dark:divide-gray-700">
                            <tr v-for="row in rowsFor(currency)" :key="row.label" class="text-gray-700 dark:text-gray-200">
                                <td class="py-2 pr-4">{{ row.label }}</td>
                                <td class="px-4 py-2 text-right text-green-600 dark:text-green-400"><Money :amount="row.income" :currency="currency.currency" /></td>
                                <td class="px-4 py-2 text-right text-red-600 dark:text-red-400"><Money :amount="row.expense" :currency="currency.currency" /></td>
                                <td class="px-4 py-2 text-right"><Money :amount="row.conservative" :currency="currency.currency" /></td>
                                <td class="px-4 py-2 text-right font-semibold"><Money :amount="row.expected" :currency="currency.currency" /></td>
                                <td class="px-4 py-2 text-right"><Money :amount="row.optimistic" :currency="currency.currency" /></td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="mt-2 text-xs text-gray-400">Planned income and expenses are shown for the Expected view. Expenses always count in full.</p>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
