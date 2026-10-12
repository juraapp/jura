<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import { useSidebar } from '@/composables/useSidebar';
import { index as accountsIndex } from '@/routes/accounts';
import { index as bucketsIndex } from '@/routes/buckets';
import { index as plannedEventsIndex } from '@/routes/planned-events';
import { index as wishlistIndex } from '@/routes/wishlist';
import { index as plannerIndex } from '@/routes/planner';
import { index as categoriesIndex } from '@/routes/categories';
import { index as dataExportIndex } from '@/routes/data-export';
import { index as expensesIndex } from '@/routes/expenses';
import { index as incomesIndex } from '@/routes/incomes';
import { edit as preferencesEdit } from '@/routes/preferences';
import { index as trashIndex } from '@/routes/trash';
import { index as recurringTransactionsIndex } from '@/routes/recurring-transactions';
import { annual as reportsAnnual, buckets as reportsBuckets, custom as reportsCustom, forecast as reportsForecast, monthly as reportsMonthly, projects as reportsProjects } from '@/routes/reports';
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
            { label: 'ui.shell.nav.dashboard', route: dashboard, icon: 'home' },
        ],
    },
    {
        title: 'ui.shell.nav.finances',
        items: [
            { label: 'ui.shell.nav.transactions', route: transactionsIndex, icon: 'list-bullet' },
            { label: 'ui.shell.nav.import_csv', route: transactionsImport, icon: 'arrow-up-tray' },
            { label: 'ui.shell.nav.incomes', route: incomesIndex, icon: 'arrow-up-circle' },
            { label: 'ui.shell.nav.expenses', route: expensesIndex, icon: 'arrow-down-circle' },
            { label: 'ui.shell.nav.transfers', route: transfersIndex, icon: 'arrows-right-left' },
        ],
    },
    {
        title: 'ui.shell.nav.accounts',
        items: [
            { label: 'ui.shell.nav.banks_wallets', route: accountsIndex, icon: 'wallet' },
        ],
    },
    {
        title: 'ui.shell.nav.planning',
        items: [
            { label: 'ui.shell.nav.cash_flow', route: plannedEventsIndex, icon: 'calendar' },
            { label: 'ui.shell.nav.buckets', route: bucketsIndex, icon: 'chart-pie' },
            { label: 'ui.shell.nav.wish_list', route: wishlistIndex, icon: 'flag' },
            { label: 'ui.shell.nav.planner', route: plannerIndex, icon: 'scale' },
            { label: 'ui.shell.nav.savings_goals', route: savingsGoalsIndex, icon: 'flag' },
            { label: 'ui.shell.nav.recurring_expenses', route: recurringTransactionsIndex, icon: 'arrow-path' },
        ],
    },
    {
        title: 'ui.shell.nav.reports',
        items: [
            { label: 'ui.shell.nav.monthly', route: reportsMonthly, icon: 'document-chart-bar' },
            { label: 'ui.shell.nav.annual', route: reportsAnnual, icon: 'document-chart-bar' },
            { label: 'ui.shell.nav.custom', route: reportsCustom, icon: 'document-chart-bar' },
            { label: 'ui.shell.nav.forecast', route: reportsForecast, icon: 'arrow-trending-up' },
            { label: 'ui.shell.nav.buckets', route: reportsBuckets, icon: 'chart-pie' },
            { label: 'ui.shell.nav.projects', route: reportsProjects, icon: 'flag' },
        ],
    },
    {
        title: 'ui.shell.nav.settings',
        items: [
            { label: 'ui.shell.nav.profile_security', route: profileEdit, icon: 'user-circle' },
            { label: 'ui.shell.nav.categories', route: categoriesIndex, icon: 'tag' },
            { label: 'ui.shell.nav.preferences', route: preferencesEdit, icon: 'adjustments' },
            { label: 'ui.shell.nav.trash', route: trashIndex, icon: 'trash' },
            { label: 'ui.shell.nav.export_data', route: dataExportIndex, icon: 'arrow-down-tray' },
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
                    {{ $t(section.title) }}
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
                            {{ $t(item.label) }}
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
