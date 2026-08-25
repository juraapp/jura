<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Money from '@/Components/Money.vue';
import StatCard from '@/Components/StatCard.vue';
import TextInput from '@/Components/TextInput.vue';
import { custom as customReport } from '@/routes/reports';
import { csv as customCsv } from '@/routes/reports/custom';

const props = defineProps({
    from: { type: String, required: true },
    to: { type: String, required: true },
    totals: { type: Array, required: true },
    transactions: { type: Array, required: true },
});

const filters = reactive({ from: props.from, to: props.to });

const applyFilters = () => {
    router.get(customReport.url({ query: { from: filters.from, to: filters.to } }), {}, { preserveState: true });
};

const csvUrl = computed(() => customCsv.url({ query: { from: filters.from, to: filters.to } }));

const amountClass = (type) => ({
    income: 'text-green-600 dark:text-green-400',
    expense: 'text-red-600 dark:text-red-400',
}[type] ?? 'text-gray-500 dark:text-gray-400');

const formatDate = (date) => new Date(date).toLocaleDateString('en-GB');
</script>

<template>
    <Head title="Custom report" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Custom report
            </h2>
        </template>

        <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div>
                    <InputLabel for="from" value="From" />
                    <TextInput id="from" v-model="filters.from" type="date" class="mt-1" @change="applyFilters" />
                </div>
                <div>
                    <InputLabel for="to" value="To" />
                    <TextInput id="to" v-model="filters.to" type="date" class="mt-1" @change="applyFilters" />
                </div>
                <a :href="csvUrl" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
                    <DynamicIcon name="arrow-down-tray" class="h-4 w-4" />
                    Export CSV
                </a>
            </div>

            <div v-for="currencyTotals in totals" :key="currencyTotals.currency" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard :label="`Income for the period (${currencyTotals.currency})`" icon="arrow-up-circle" icon-color="text-green-600 dark:text-green-400" icon-bg="bg-green-50 dark:bg-green-500/10">
                    <Money :amount="currencyTotals.income" :currency="currencyTotals.currency" />
                </StatCard>
                <StatCard :label="`Expenses for the period (${currencyTotals.currency})`" icon="arrow-down-circle" icon-color="text-red-600 dark:text-red-400" icon-bg="bg-red-50 dark:bg-red-500/10">
                    <Money :amount="currencyTotals.expense" :currency="currencyTotals.currency" />
                </StatCard>
                <StatCard :label="`Savings for the period (${currencyTotals.currency})`" icon="chart-pie">
                    <Money :amount="currencyTotals.savings" :currency="currencyTotals.currency" />
                </StatCard>
            </div>

            <EmptyState v-if="transactions.length === 0" icon="list-bullet" title="No transactions in this date range" />

            <div v-else class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Description</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Account</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="transaction in transactions" :key="transaction.id">
                                <td class="whitespace-nowrap px-4 py-2.5 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(transaction.date) }}</td>
                                <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300">{{ transaction.description || transaction.category }}</td>
                                <td class="px-4 py-2.5 text-sm text-gray-500 dark:text-gray-400">{{ transaction.account }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-sm font-medium tabular-nums" :class="amountClass(transaction.type)">
                                    <Money :amount="transaction.amount" :currency="transaction.currency" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
