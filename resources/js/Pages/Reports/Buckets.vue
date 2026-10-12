<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Money from '@/Components/Money.vue';
import SegmentedControl from '@/Components/SegmentedControl.vue';
import { useDarkMode } from '@/composables/useDarkMode';
import { CHART_PALETTE, formatCompact, formatMoney } from '@/chartPalette';
import { index as bucketsIndex } from '@/routes/buckets';
import { buckets as bucketsReport } from '@/routes/reports';
import { useFormat } from '@/composables/useFormat';

const format = useFormat();

const props = defineProps({
    currencies: { type: Array, required: true },
    view: { type: String, required: true },
    views: { type: Array, required: true },
});

const page = usePage();
const { isDark } = useDarkMode();
const locale = () => page.props.auth.user?.locale ?? 'en';
const palette = () => CHART_PALETTE[isDark.value ? 'dark' : 'light'];

const changeView = (value) => {
    router.get(bucketsReport.url({ query: { view: value } }), {}, { preserveScroll: true, preserveState: true });
};

const viewHints = {
    conservative: 'Projected with confirmed income only.',
    expected: 'Projected with confirmed and expected income.',
    optimistic: 'Projected with all planned income.',
};

const balanceOptions = (currency) => ({
    chart: { type: 'bar', height: Math.max(180, currency.buckets.length * 46), fontFamily: 'Roboto, sans-serif', toolbar: { show: false } },
    plotOptions: { bar: { horizontal: true, distributed: true, borderRadius: 4, barHeight: '55%' } },
    colors: palette().series,
    xaxis: { categories: currency.buckets.map((bucket) => bucket.name), labels: { style: { colors: palette().text }, formatter: (v) => formatCompact(v, locale()) } },
    yaxis: { labels: { style: { colors: palette().text } } },
    grid: { borderColor: palette().grid },
    legend: { show: false },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: (v) => formatMoney(v, currency.currency, locale()) } },
});

const historyOptions = (currency) => ({
    chart: { type: 'bar', stacked: true, height: 300, fontFamily: 'Roboto, sans-serif', toolbar: { show: false } },
    colors: palette().series,
    plotOptions: { bar: { columnWidth: '50%' } },
    xaxis: { categories: currency.history.labels, labels: { style: { colors: palette().text } } },
    yaxis: { labels: { style: { colors: palette().text }, formatter: (v) => formatCompact(v, locale()) } },
    grid: { borderColor: palette().grid },
    legend: { labels: { colors: palette().text } },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: (v) => formatMoney(v, currency.currency, locale()) } },
});

const targetPercent = (bucket) => (bucket.cap ? Math.max(0, Math.min(100, (bucket.available / bucket.cap) * 100)) : null);
</script>

<template>
    <Head title="Buckets report" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Buckets report</h2>
        </template>

        <div class="mx-auto max-w-6xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
            <EmptyState
                v-if="currencies.length === 0"
                icon="chart-pie"
                title="No buckets yet"
                description="Create buckets to set money aside, and this report will show how they are funded and where they are heading."
            >
                <template #action>
                    <Link :href="bucketsIndex.url()" class="text-sm font-semibold text-primary-600 hover:underline">Go to Buckets</Link>
                </template>
            </EmptyState>

            <template v-else>
                <div>
                    <SegmentedControl :model-value="view" :options="views" @update:model-value="changeView" />
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ viewHints[view] }} Applies to the projected balances below.</p>
                </div>

                <section v-for="currency in currencies" :key="currency.currency" class="space-y-4">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ currency.currency }}</h3>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <h4 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Available funds today</h4>
                            <apexchart
                                :key="`bal-${currency.currency}-${isDark}`"
                                type="bar"
                                :height="Math.max(180, currency.buckets.length * 46)"
                                :options="balanceOptions(currency)"
                                :series="[{ name: 'Available', data: currency.buckets.map((bucket) => bucket.available) }]"
                            />
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <h4 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Money in and out, by month</h4>
                            <p v-if="currency.history.series.length === 0" class="py-12 text-center text-sm text-gray-400">No movements in the last 6 months.</p>
                            <apexchart
                                v-else
                                :key="`hist-${currency.currency}-${isDark}`"
                                type="bar"
                                height="300"
                                :options="historyOptions(currency)"
                                :series="currency.history.series"
                            />
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-3 font-semibold">Bucket</th>
                                    <th class="px-4 py-3 text-right font-semibold">Available now</th>
                                    <th class="px-4 py-3 text-right font-semibold">Target</th>
                                    <th class="px-4 py-3 text-right font-semibold">In 12 months (projected)</th>
                                    <th class="px-4 py-3 text-right font-semibold">Change</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 tabular-nums dark:divide-gray-700">
                                <tr v-for="bucket in currency.buckets" :key="bucket.id" class="text-gray-700 dark:text-gray-200">
                                    <td class="px-4 py-2 font-medium">{{ bucket.name }}<span v-if="bucket.isDefault" class="ml-1.5 rounded-full bg-primary-50 px-1.5 py-0.5 text-xs text-primary-700 dark:bg-primary-500/10 dark:text-primary-400">Default</span></td>
                                    <td class="px-4 py-2 text-right"><Money :amount="bucket.available" :currency="currency.currency" /></td>
                                    <td class="px-4 py-2 text-right">
                                        <template v-if="bucket.cap && bucket.kind === 'savings'"><Money :amount="bucket.cap" :currency="currency.currency" /> <span class="text-xs text-gray-400">({{ format.number(targetPercent(bucket), 0) }}%)</span></template>
                                        <span v-else-if="bucket.cap" class="text-xs text-gray-400">limit <Money :amount="bucket.cap" :currency="currency.currency" /> per cycle</span>
                                        <span v-else class="text-gray-300">-</span>
                                    </td>
                                    <template v-for="row in currency.projected.filter((candidate) => candidate.id === bucket.id)" :key="row.id">
                                        <td class="px-4 py-2 text-right font-semibold"><Money :amount="row.projected" :currency="currency.currency" /></td>
                                        <td class="px-4 py-2 text-right" :class="row.projected - row.now >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                            <Money :amount="row.projected - row.now" :currency="currency.currency" signed />
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
