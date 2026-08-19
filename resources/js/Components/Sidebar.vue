<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import { useSidebar } from '@/composables/useSidebar';
import { index as accountsIndex } from '@/routes/accounts';
import { index as budgetsIndex } from '@/routes/budgets';
import { index as categoriesIndex } from '@/routes/categories';
import { index as dataExportIndex } from '@/routes/data-export';
import { index as expensesIndex } from '@/routes/expenses';
import { index as incomesIndex } from '@/routes/incomes';
import { edit as preferencesEdit } from '@/routes/preferences';
import { index as recurringTransactionsIndex } from '@/routes/recurring-transactions';
import { annual as reportsAnnual, custom as reportsCustom, monthly as reportsMonthly } from '@/routes/reports';
import { index as savingsGoalsIndex } from '@/routes/savings-goals';
import { importMethod as transactionsImport, index as transactionsIndex } from '@/routes/transactions';
import { index as transfersIndex } from '@/routes/transfers';
import { dashboard } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';

const page = usePage();
const { isOpen, close } = useSidebar();

const sections = [
    {
        items: [
            { label: 'Dashboard', route: dashboard, icon: 'home' },
        ],
    },
    {
        title: 'Finances',
        items: [
            { label: 'Transactions', route: transactionsIndex, icon: 'list-bullet' },
            { label: 'Import CSV', route: transactionsImport, icon: 'arrow-up-tray' },
            { label: 'Incomes', route: incomesIndex, icon: 'arrow-up-circle' },
            { label: 'Expenses', route: expensesIndex, icon: 'arrow-down-circle' },
            { label: 'Transfers', route: transfersIndex, icon: 'arrows-right-left' },
        ],
    },
    {
        title: 'Accounts',
        items: [
            { label: 'Banks & wallets', route: accountsIndex, icon: 'wallet' },
        ],
    },
    {
        title: 'Planning',
        items: [
            { label: 'Budgets', route: budgetsIndex, icon: 'chart-pie' },
            { label: 'Savings goals', route: savingsGoalsIndex, icon: 'flag' },
            { label: 'Recurring expenses', route: recurringTransactionsIndex, icon: 'arrow-path' },
        ],
    },
    {
        title: 'Reports',
        items: [
            { label: 'Monthly', route: reportsMonthly, icon: 'document-chart-bar' },
            { label: 'Annual', route: reportsAnnual, icon: 'document-chart-bar' },
            { label: 'Custom', route: reportsCustom, icon: 'document-chart-bar' },
        ],
    },
    {
        title: 'Settings',
        items: [
            { label: 'Profile & security', route: profileEdit, icon: 'user-circle' },
            { label: 'Categories', route: categoriesIndex, icon: 'tag' },
            { label: 'Preferences', route: preferencesEdit, icon: 'adjustments' },
            { label: 'Export my data', route: dataExportIndex, icon: 'arrow-down-tray' },
        ],
    },
];

const isActive = (routeFn) => computed(() => page.url === routeFn.url());
</script>

<template>
    <aside
        class="fixed inset-y-0 left-0 z-40 w-64 shrink-0 transform border-r border-gray-200 bg-white transition-transform duration-200 ease-in-out lg:static lg:translate-x-0 dark:border-gray-700 dark:bg-gray-800"
        :class="isOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    >
        <div class="flex h-16 items-center gap-2 border-b border-gray-200 px-5 dark:border-gray-700">
            <ApplicationLogo class="h-8 w-8 fill-current text-primary-600 dark:text-primary-400" />
            <span class="text-lg font-semibold text-gray-900 dark:text-white">{{ $page.props.appName }}</span>
        </div>

        <nav class="h-[calc(100%-4rem)] space-y-6 overflow-y-auto px-3 py-5">
            <div v-for="(section, index) in sections" :key="index">
                <p
                    v-if="section.title"
                    class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500"
                >
                    {{ section.title }}
                </p>

                <ul class="space-y-1">
                    <li v-for="item in section.items" :key="item.label">
                        <Link
                            :href="item.route.url()"
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="isActive(item.route).value
                                ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400'
                                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700/50'"
                            @click="close"
                        >
                            <DynamicIcon :name="item.icon" class="h-5 w-5 shrink-0" />
                            {{ item.label }}
                        </Link>
                    </li>
                </ul>
            </div>
        </nav>
    </aside>

    <div
        v-show="isOpen"
        class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden"
        @click="close"
    ></div>
</template>
