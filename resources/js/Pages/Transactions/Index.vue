<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CategoryIcon from '@/Components/CategoryIcon.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Money from '@/Components/Money.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { useTransactionModal } from '@/composables/useTransactionModal';
import { destroy } from '@/actions/App/Http/Controllers/TransactionController';

const props = defineProps({
    typeFilter: { type: String, required: true },
    transactions: { type: Object, required: true },
    accounts: { type: Array, required: true },
    categories: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const { open: openTransactionModal } = useTransactionModal();
const confirmingDeleteTransaction = ref(null);

const filters = reactive({
    q: props.filters.q ?? '',
    account: props.filters.account ?? '',
    category: props.filters.category ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

const pageTitle = computed(() => ({
    income: 'Incomes',
    expense: 'Expenses',
    transfer: 'Transfers',
}[props.typeFilter] ?? 'Transactions'));

let searchDebounce = null;

const applyFilters = () => {
    router.get(window.location.pathname, { ...filters }, { preserveState: true, preserveScroll: true, replace: true });
};

const onSearchInput = () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(applyFilters, 400);
};

const confirmDelete = (transaction) => {
    confirmingDeleteTransaction.value = transaction;
};

const deleteTransaction = () => {
    router.delete(destroy.url(confirmingDeleteTransaction.value.id), {
        preserveScroll: true,
        onFinish: () => (confirmingDeleteTransaction.value = null),
    });
};

const amountClass = (type) => ({
    income: 'text-green-600 dark:text-green-400',
    expense: 'text-red-600 dark:text-red-400',
}[type] ?? 'text-gray-500 dark:text-gray-400');

const amountSign = (type) => ({ income: '+', expense: '−' }[type] ?? '');

const formatDate = (date) => new Date(date).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ pageTitle }}
            </h2>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-4 grid grid-cols-2 gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:grid-cols-3 lg:grid-cols-6 dark:border-gray-700 dark:bg-gray-800">
                <div class="col-span-2 sm:col-span-1 lg:col-span-2">
                    <input
                        v-model="filters.q"
                        type="text"
                        placeholder="Search description..."
                        class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        @input="onSearchInput"
                    />
                </div>

                <select
                    v-model="filters.account"
                    class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    @change="applyFilters"
                >
                    <option value="">All accounts</option>
                    <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                </select>

                <select
                    v-model="filters.category"
                    class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    @change="applyFilters"
                >
                    <option value="">All categories</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                </select>

                <input
                    v-model="filters.from"
                    type="date"
                    class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    @change="applyFilters"
                />
                <input
                    v-model="filters.to"
                    type="date"
                    class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    @change="applyFilters"
                />
            </div>

            <EmptyState
                v-if="transactions.data.length === 0"
                icon="list-bullet"
                title="No transactions for these filters"
                description="Record your first movement or adjust the search filters."
            >
                <template #action>
                    <PrimaryButton type="button" @click="openTransactionModal(null, 'expense')">
                        <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                        New transaction
                    </PrimaryButton>
                </template>
            </EmptyState>

            <template v-else>
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/40">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Description</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Account</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Amount</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="transaction in transactions.data" :key="transaction.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDate(transaction.date) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <CategoryIcon v-if="transaction.category" :category="transaction.category" size="h-7 w-7" />
                                            <span v-else class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                                                <DynamicIcon name="arrows-right-left" class="h-3.5 w-3.5" />
                                            </span>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ transaction.description || transaction.category?.name || 'Transfer' }}
                                                </p>
                                                <p class="text-xs text-gray-400">
                                                    {{ transaction.category?.name || 'Transfer between accounts' }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                        {{ transaction.account.name }}
                                        <span v-if="typeFilter === 'transfer' && transaction.transferDestinationAccount" class="text-gray-400">
                                            &rarr; {{ transaction.transferDestinationAccount }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold tabular-nums" :class="amountClass(transaction.type)">
                                        {{ amountSign(transaction.type) }}
                                        <Money :amount="transaction.amount" :currency="transaction.account.currency" />
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                                                @click="openTransactionModal(transaction.id)"
                                            >
                                                <DynamicIcon name="pencil" class="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                                @click="confirmDelete(transaction)"
                                            >
                                                <DynamicIcon name="trash" class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4">
                    <Pagination :links="transactions.links" />
                </div>
            </template>
        </div>

        <Modal :show="confirmingDeleteTransaction !== null" @close="confirmingDeleteTransaction = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delete this transaction?</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    If this is a transfer, both movements (source and destination) will be deleted. This action cannot be undone.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="confirmingDeleteTransaction = null">Cancel</SecondaryButton>
                    <DangerButton type="button" @click="deleteTransaction">Delete</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
