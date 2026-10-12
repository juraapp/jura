<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatCard from '@/Components/StatCard.vue';
import BadgeVariation from '@/Components/BadgeVariation.vue';
import Money from '@/Components/Money.vue';
import { useDarkMode } from '@/composables/useDarkMode';
import { CHART_PALETTE, formatCompact, formatMoney } from '@/chartPalette';
import { index as accountsIndex } from '@/routes/accounts';
import { index as plannedEventsIndex } from '@/routes/planned-events';
import { index as transactionsIndex } from '@/routes/transactions';
import { dashboard } from '@/routes';
import { useFormat } from '@/composables/useFormat';

const format = useFormat();
const { t } = useI18n();

const props = defineProps({
    evolutionMonths: { type: Number, required: true },
    hasAnyAccount: { type: Boolean, required: true },
    hasAnyTransaction: { type: Boolean, required: true },
    currencies: { type: Array, required: true },
    upcoming: { type: Array, default: () => [] },
});

const page = usePage();
const { isDark } = useDarkMode();
const locale = () => page.props.auth.user?.locale ?? 'en';
const palette = () => CHART_PALETTE[isDark.value ? 'dark' : 'light'];

const setEvolutionMonths = (months) => {
    router.get(dashboard.url({ query: { evolutionMonths: months } }), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['currencies', 'evolutionMonths'],
    });
};

const expenseByCategoryOptions = (currency) => ({
    chart: { type: 'donut', height: 288, fontFamily: 'Roboto, sans-serif' },
    labels: currency.expenseByCategory.map((row) => row.name ?? t('ui.dashboard.uncategorized')),
    colors: currency.expenseByCategory.map((row) => row.color),
    legend: { position: 'bottom', labels: { colors: palette().text } },
    dataLabels: { enabled: false },
    stroke: { width: 2, colors: [isDark.value ? '#1f2937' : '#ffffff'] },
    tooltip: { y: { formatter: (v) => formatMoney(v, currency.currency, locale()) } },
});

const monthlyEvolutionOptions = (currency) => ({
    chart: { type: 'line', height: 288, fontFamily: 'Roboto, sans-serif', toolbar: { show: false } },
    colors: [palette().income, palette().expense, palette().savings],
    xaxis: { categories: currency.monthlyEvolution.map((row) => row.label), labels: { style: { colors: palette().text } } },
    yaxis: { labels: { style: { colors: palette().text }, formatter: (v) => formatCompact(v, locale()) } },
    grid: { borderColor: palette().grid },
    stroke: { width: 2, curve: 'smooth' },
    legend: { labels: { colors: palette().text } },
    tooltip: { y: { formatter: (v) => formatMoney(v, currency.currency, locale()) } },
});

const monthlyEvolutionSeries = (currency) => [
    { name: t('ui.dashboard.income'), data: currency.monthlyEvolution.map((row) => row.income) },
    { name: t('ui.dashboard.expenses'), data: currency.monthlyEvolution.map((row) => row.expense) },
    { name: t('ui.dashboard.savings'), data: currency.monthlyEvolution.map((row) => row.savings) },
];

const expenseByAccountOptions = (currency) => ({
    chart: { type: 'bar', height: 288, fontFamily: 'Roboto, sans-serif', toolbar: { show: false } },
    plotOptions: { bar: { horizontal: true, distributed: true, borderRadius: 4, barHeight: '55%' } },
    colors: currency.expenseByAccount.map((row) => row.color),
    xaxis: {
        categories: currency.expenseByAccount.map((row) => row.name),
        labels: { style: { colors: palette().text }, formatter: (v) => formatCompact(v, locale()) },
    },
    yaxis: { labels: { style: { colors: palette().text } } },
    grid: { borderColor: palette().grid },
    legend: { show: false },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: (v) => formatMoney(v, currency.currency, locale()) } },
});

const moneyDistributionOptions = (currency) => ({
    chart: { type: 'donut', height: 288, fontFamily: 'Roboto, sans-serif' },
    labels: currency.moneyDistribution.map((row) => row.name),
    colors: currency.moneyDistribution.map((row) => row.color),
    legend: { position: 'bottom', labels: { colors: palette().text } },
    dataLabels: { enabled: false },
    stroke: { width: 2, colors: [isDark.value ? '#1f2937' : '#ffffff'] },
    tooltip: { y: { formatter: (v) => formatMoney(v, currency.currency, locale()) } },
});

const netWorthEvolutionOptions = (currency) => ({
    chart: { type: 'area', height: 260, fontFamily: 'Roboto, sans-serif', toolbar: { show: false } },
    colors: [palette().series[0]],
    fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
    xaxis: { categories: currency.netWorthEvolution.map((row) => row.label), labels: { style: { colors: palette().text } } },
    yaxis: { labels: { style: { colors: palette().text }, formatter: (v) => formatCompact(v, locale()) } },
    grid: { borderColor: palette().grid },
    stroke: { width: 2, curve: 'smooth' },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: (v) => formatMoney(v, currency.currency, locale()) } },
});
</script>

<template>
    <Head :title="$t('ui.dashboard.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ $t('ui.dashboard.title') }}
            </h2>
        </template>

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <EmptyState
                v-if="!hasAnyAccount"
                icon="wallet"
                :title="$t('ui.dashboard.no_account_title')"
                :description="$t('ui.dashboard.no_account_hint')"
            >
                <template #action>
                    <Link
                        :href="accountsIndex().url"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        <DynamicIcon name="plus" class="h-4 w-4" />
                        {{ $t('ui.dashboard.create_account') }}
                    </Link>
                </template>
            </EmptyState>

            <EmptyState
                v-else-if="!hasAnyTransaction"
                icon="list-bullet"
                :title="$t('ui.dashboard.no_tx_title')"
                :description="$t('ui.dashboard.no_tx_hint')"
            >
                <template #action>
                    <Link
                        :href="transactionsIndex().url"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        <DynamicIcon name="plus" class="h-4 w-4" />
                        {{ $t('ui.dashboard.new_transaction') }}
                    </Link>
                </template>
            </EmptyState>

            <template v-else>
                <!-- Total net worth never mixes currencies, one line per currency -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard :label="$t('ui.dashboard.total_net_worth')" icon="banknotes">
                        <div v-for="currency in currencies" :key="currency.currency">
                            <Money :amount="currency.netWorth" :currency="currency.currency" />
                        </div>
                    </StatCard>
                </div>

                <!-- Projected, not recorded: kept visually apart from the real figures below -->
                <div v-if="upcoming.length" class="rounded-xl border border-dashed border-primary-300 bg-primary-50/40 p-4 dark:border-primary-500/40 dark:bg-primary-500/5">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-primary-800 dark:text-primary-300">{{ $t('ui.dashboard.next_30') }}</h3>
                        <Link :href="plannedEventsIndex.url()" class="text-xs font-medium text-primary-700 hover:underline dark:text-primary-400">{{ $t('ui.dashboard.open_cash_flow') }}</Link>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div v-for="item in upcoming" :key="item.currency" class="contents">
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.dashboard.expected_income') }} <span v-if="upcoming.length > 1">({{ item.currency }})</span></p>
                                <p class="text-lg font-semibold text-green-600 dark:text-green-400"><Money :amount="item.income" :currency="item.currency" /></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.dashboard.planned_expenses') }}</p>
                                <p class="text-lg font-semibold text-red-600 dark:text-red-400"><Money :amount="item.expense" :currency="item.currency" /></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.dashboard.net') }}</p>
                                <p class="text-lg font-semibold text-gray-900 dark:text-white"><Money :amount="item.net" :currency="item.currency" signed /></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- The rest of the dashboard repeats per currency you use, so amounts in different currencies are never summed -->
                <div v-for="currency in currencies" :key="currency.currency" class="space-y-6">
                    <h3
                        v-if="currencies.length > 1"
                        class="border-t border-gray-200 pt-6 text-sm font-semibold uppercase tracking-wide text-gray-400 dark:border-gray-700"
                    >
                        {{ currency.currency }}
                    </h3>

                    <!-- Row 1: summary cards -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard :label="$t('ui.dashboard.income_month')" icon="arrow-up-circle" icon-color="text-green-600 dark:text-green-400" icon-bg="bg-green-50 dark:bg-green-500/10">
                            <Money :amount="currency.summary.income" :currency="currency.currency" />
                            <template #footer>
                                <BadgeVariation :value="currency.summary.changeIncome" :favorable-when-positive="true" />
                                <span class="ms-1 text-gray-400">{{ $t('ui.dashboard.vs_last') }}</span>
                            </template>
                        </StatCard>

                        <StatCard :label="$t('ui.dashboard.expenses_month')" icon="arrow-down-circle" icon-color="text-red-600 dark:text-red-400" icon-bg="bg-red-50 dark:bg-red-500/10">
                            <Money :amount="currency.summary.expense" :currency="currency.currency" />
                            <template #footer>
                                <BadgeVariation :value="currency.summary.changeExpense" :favorable-when-positive="false" />
                                <span class="ms-1 text-gray-400">{{ $t('ui.dashboard.vs_last') }}</span>
                            </template>
                        </StatCard>

                        <StatCard :label="$t('ui.dashboard.savings_month')" icon="chart-pie">
                            <Money :amount="currency.summary.savings" :currency="currency.currency" />
                            <template #footer>
                                <span
                                    class="font-medium"
                                    :class="currency.summary.savings < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'"
                                >
                                    {{ format.number(currency.summary.savingsRate, 1) }}%
                                </span>
                                <span class="ms-1 text-gray-400">{{ $t('ui.dashboard.savings_rate') }}</span>
                            </template>
                        </StatCard>

                        <StatCard :label="$t('ui.dashboard.avg_daily')" icon="calendar">
                            <Money :amount="currency.summary.avgDailyExpense" :currency="currency.currency" />
                        </StatCard>

                        <StatCard :label="$t('ui.dashboard.biggest')" icon="arrow-trending-up">
                            <Money v-if="currency.summary.biggestExpense" :amount="currency.summary.biggestExpense.amount" :currency="currency.currency" />
                            <span v-else class="text-gray-400">&mdash;</span>
                            <template v-if="currency.summary.biggestExpense" #footer>
                                <span class="text-gray-400">{{ currency.summary.biggestExpense.description }}</span>
                            </template>
                        </StatCard>

                        <StatCard :label="$t('ui.dashboard.top_category')" icon="tag">
                            <template v-if="currency.summary.topCategory">{{ currency.summary.topCategory.name }}</template>
                            <span v-else class="text-gray-400">&mdash;</span>
                            <template v-if="currency.summary.topCategory" #footer>
                                <span class="text-gray-400">{{ $t('ui.dashboard.percent_of_expenses', { percent: format.number(currency.summary.topCategory.percentage, 1) }) }}</span>
                            </template>
                        </StatCard>

                        <StatCard :label="$t('ui.dashboard.autonomy')" icon="flag">
                            <template v-if="currency.summary.daysOfAutonomy !== null">{{ $t('ui.dashboard.days', { count: format.number(currency.summary.daysOfAutonomy, 0) }) }}</template>
                            <span v-else class="text-gray-400">&mdash;</span>
                            <template v-if="currency.summary.daysOfAutonomy !== null" #footer>
                                <span class="text-gray-400">{{ $t('ui.dashboard.pace') }}</span>
                            </template>
                        </StatCard>
                    </div>

                    <!-- Automatic insights -->
                    <div v-if="currency.highlights.length" class="rounded-xl border border-primary-100 bg-primary-50/60 p-5 dark:border-primary-500/20 dark:bg-primary-500/5">
                        <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold text-primary-800 dark:text-primary-300">
                            <DynamicIcon name="document-chart-bar" class="h-4 w-4" />
                            {{ $t('ui.dashboard.where_money') }}
                        </h3>
                        <ul class="space-y-1 text-sm text-gray-700 dark:text-gray-300">
                            <li v-for="(line, index) in currency.highlights" :key="index" class="flex gap-2">
                                <span class="text-primary-500">&bull;</span>
                                {{ line }}
                            </li>
                        </ul>
                    </div>

                    <!-- Charts -->
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $t('ui.dashboard.by_category') }}</h3>
                            <p v-if="!currency.expenseByCategory.length" class="py-12 text-center text-sm text-gray-400">{{ $t('ui.dashboard.no_expenses') }}</p>
                            <apexchart
                                v-else
                                :key="`category-${currency.currency}-${isDark}`"
                                type="donut"
                                height="288"
                                :options="expenseByCategoryOptions(currency)"
                                :series="currency.expenseByCategory.map((row) => row.total)"
                            />
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $t('ui.dashboard.evolution') }}</h3>
                                <div class="flex gap-1">
                                    <button
                                        v-for="option in [{ value: 6, label: '6M' }, { value: 12, label: '12M' }]"
                                        :key="option.value"
                                        type="button"
                                        class="rounded-lg px-2 py-1 text-xs font-medium"
                                        :class="evolutionMonths === option.value
                                            ? 'bg-primary-600 text-white'
                                            : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'"
                                        @click="setEvolutionMonths(option.value)"
                                    >
                                        {{ option.label }}
                                    </button>
                                </div>
                            </div>
                            <apexchart
                                :key="`evolution-${currency.currency}-${isDark}-${evolutionMonths}`"
                                type="line"
                                height="288"
                                :options="monthlyEvolutionOptions(currency)"
                                :series="monthlyEvolutionSeries(currency)"
                            />
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $t('ui.dashboard.by_account') }}</h3>
                            <p v-if="!currency.expenseByAccount.length" class="py-12 text-center text-sm text-gray-400">{{ $t('ui.dashboard.no_expenses') }}</p>
                            <apexchart
                                v-else
                                :key="`account-${currency.currency}-${isDark}`"
                                type="bar"
                                height="288"
                                :options="expenseByAccountOptions(currency)"
                                :series="[{ name: t('ui.dashboard.expense'), data: currency.expenseByAccount.map((row) => row.total) }]"
                            />
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $t('ui.dashboard.distribution') }}</h3>
                            <p v-if="!currency.moneyDistribution.length" class="py-12 text-center text-sm text-gray-400">{{ $t('ui.dashboard.no_positive') }}</p>
                            <apexchart
                                v-else
                                :key="`distribution-${currency.currency}-${isDark}`"
                                type="donut"
                                height="288"
                                :options="moneyDistributionOptions(currency)"
                                :series="currency.moneyDistribution.map((row) => row.balance)"
                            />
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2 dark:border-gray-700 dark:bg-gray-800">
                            <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $t('ui.dashboard.net_worth_evolution') }}</h3>
                            <apexchart
                                :key="`net-worth-${currency.currency}-${isDark}-${evolutionMonths}`"
                                type="area"
                                height="260"
                                :options="netWorthEvolutionOptions(currency)"
                                :series="[{ name: t('ui.dashboard.net_worth'), data: currency.netWorthEvolution.map((row) => row.netWorth) }]"
                            />
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
