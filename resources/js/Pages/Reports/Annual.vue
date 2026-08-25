<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import Money from '@/Components/Money.vue';
import StatCard from '@/Components/StatCard.vue';
import { annual as annualReport } from '@/routes/reports';
import { pdf as annualPdf } from '@/routes/reports/annual';

const props = defineProps({
    year: { type: Number, required: true },
    currencies: { type: Array, required: true },
});

const previousYear = () => router.get(annualReport.url({ query: { year: props.year - 1 } }));
const nextYear = () => router.get(annualReport.url({ query: { year: props.year + 1 } }));

const pdfUrl = computed(() => annualPdf.url({ query: { year: props.year } }));
</script>

<template>
    <Head title="Annual report" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Annual report
                </h2>
                <div class="flex items-center gap-2">
                    <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700" @click="previousYear">
                        <DynamicIcon name="chevron-right" class="h-4 w-4 -scale-x-100" />
                    </button>
                    <span class="min-w-[4rem] text-center text-sm font-medium text-gray-700 dark:text-gray-300">{{ year }}</span>
                    <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700" @click="nextYear">
                        <DynamicIcon name="chevron-right" class="h-4 w-4" />
                    </button>
                    <a :href="pdfUrl" target="_blank" class="ms-2 inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
                        <DynamicIcon name="arrow-down-tray" class="h-4 w-4" />
                        Export PDF
                    </a>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
            <div v-for="currencySummary in currencies" :key="currencySummary.currency" class="space-y-6">
                <h3 v-if="currencies.length > 1" class="border-t border-gray-200 pt-6 text-sm font-semibold uppercase tracking-wide text-gray-400 dark:border-gray-700">
                    {{ currencySummary.currency }}
                </h3>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <StatCard label="Total income" icon="arrow-up-circle" icon-color="text-green-600 dark:text-green-400" icon-bg="bg-green-50 dark:bg-green-500/10">
                        <Money :amount="currencySummary.income" :currency="currencySummary.currency" />
                    </StatCard>
                    <StatCard label="Total expenses" icon="arrow-down-circle" icon-color="text-red-600 dark:text-red-400" icon-bg="bg-red-50 dark:bg-red-500/10">
                        <Money :amount="currencySummary.expense" :currency="currencySummary.currency" />
                    </StatCard>
                    <StatCard label="Total savings" icon="chart-pie">
                        <Money :amount="currencySummary.savings" :currency="currencySummary.currency" />
                        <template #footer>{{ currencySummary.savingsRate.toFixed(1) }}% annual savings rate</template>
                    </StatCard>
                    <StatCard label="Monthly average" icon="calendar">
                        <Money :amount="currencySummary.avgMonthlyExpense" :currency="currencySummary.currency" />
                        <template #footer>in expenses</template>
                    </StatCard>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div v-if="currencySummary.bestIncomeMonth" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Highest income month</p>
                        <p class="mt-1 text-lg font-semibold capitalize text-gray-900 dark:text-white">{{ currencySummary.bestIncomeMonth.label }}</p>
                        <p class="text-sm text-gray-500"><Money :amount="currencySummary.bestIncomeMonth.income" :currency="currencySummary.currency" /></p>
                    </div>
                    <div v-if="currencySummary.worstExpenseMonth" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Highest expense month</p>
                        <p class="mt-1 text-lg font-semibold capitalize text-gray-900 dark:text-white">{{ currencySummary.worstExpenseMonth.label }}</p>
                        <p class="text-sm text-gray-500"><Money :amount="currencySummary.worstExpenseMonth.expense" :currency="currencySummary.currency" /></p>
                    </div>
                    <div v-if="currencySummary.topCategory" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Category you spent the most on</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ currencySummary.topCategory.name }}</p>
                        <p class="text-sm text-gray-500">{{ currencySummary.topCategory.percentage.toFixed(1) }}% of total</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Month</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Income</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Expenses</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Savings</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="row in currencySummary.months" :key="row.label">
                                <td class="px-4 py-2.5 text-sm capitalize text-gray-700 dark:text-gray-300">{{ row.label }}</td>
                                <td class="px-4 py-2.5 text-right text-sm tabular-nums text-green-600 dark:text-green-400"><Money :amount="row.income" :currency="currencySummary.currency" /></td>
                                <td class="px-4 py-2.5 text-right text-sm tabular-nums text-red-600 dark:text-red-400"><Money :amount="row.expense" :currency="currencySummary.currency" /></td>
                                <td class="px-4 py-2.5 text-right text-sm font-medium tabular-nums text-gray-900 dark:text-white"><Money :amount="row.savings" :currency="currencySummary.currency" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
