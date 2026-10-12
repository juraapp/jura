<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Modal from '@/Components/Modal.vue';
import Money from '@/Components/Money.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import {
    restoreTransaction,
    purgeTransaction,
    restoreAccount,
    purgeAccount,
    restoreCategory,
    purgeCategory,
} from '@/actions/App/Modules/Ledger/Http/Controllers/TrashController';

const props = defineProps({
    transactions: { type: Array, required: true },
    accounts: { type: Array, required: true },
    categories: { type: Array, required: true },
});

const isEmpty = () => !props.transactions.length && !props.accounts.length && !props.categories.length;

const restore = (action, id) => router.post(action.url(id), {}, { preserveScroll: true });
const confirmingPurge = ref(null);
const purge = (action, id, label) => (confirmingPurge.value = { action, id, label });
const confirmPurge = () => {
    router.delete(confirmingPurge.value.action.url(confirmingPurge.value.id), {
        preserveScroll: true,
        onFinish: () => (confirmingPurge.value = null),
    });
};
const formatDate = (date) => new Date(`${date}T00:00:00`).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <Head title="Trash" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Trash</h2>
        </template>

        <div class="space-y-6 py-6">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Deleted items stay here until you restore them or delete them permanently.
            </p>

            <EmptyState v-if="isEmpty()" title="The trash is empty" description="Things you delete will show up here so you can bring them back." icon="trash" />

            <section v-if="transactions.length">
                <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Transactions</h3>
                <ul class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                    <li v-for="transaction in transactions" :key="transaction.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ transaction.description || transaction.category || 'Transfer' }}</p>
                            <p class="text-xs text-gray-400">{{ formatDate(transaction.date) }} · {{ transaction.account }}</p>
                        </div>
                        <Money :amount="transaction.amount" :currency="transaction.currency" class="text-sm font-semibold text-gray-700 dark:text-gray-200" />
                        <div class="flex items-center gap-1">
                            <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" title="Restore" @click="restore(restoreTransaction, transaction.id)">
                                <DynamicIcon name="arrow-path" class="h-4 w-4" />
                            </button>
                            <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" title="Delete permanently" @click="purge(purgeTransaction, transaction.id, 'this transaction')">
                                <DynamicIcon name="trash" class="h-4 w-4" />
                            </button>
                        </div>
                    </li>
                </ul>
            </section>

            <section v-if="accounts.length">
                <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Accounts</h3>
                <ul class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                    <li v-for="account in accounts" :key="account.id" class="flex items-center gap-3 px-4 py-3">
                        <p class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900 dark:text-white">{{ account.name }} <span class="text-xs text-gray-400">{{ account.currency }}</span></p>
                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" title="Restore" @click="restore(restoreAccount, account.id)">
                            <DynamicIcon name="arrow-path" class="h-4 w-4" />
                        </button>
                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" title="Delete permanently" @click="purge(purgeAccount, account.id, account.name)">
                            <DynamicIcon name="trash" class="h-4 w-4" />
                        </button>
                    </li>
                </ul>
            </section>

            <section v-if="categories.length">
                <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Categories</h3>
                <ul class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                    <li v-for="category in categories" :key="category.id" class="flex items-center gap-3 px-4 py-3">
                        <p class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900 dark:text-white">{{ category.name }} <span class="text-xs capitalize text-gray-400">{{ category.type }}</span></p>
                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" title="Restore" @click="restore(restoreCategory, category.id)">
                            <DynamicIcon name="arrow-path" class="h-4 w-4" />
                        </button>
                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" title="Delete permanently" @click="purge(purgeCategory, category.id, category.name)">
                            <DynamicIcon name="trash" class="h-4 w-4" />
                        </button>
                    </li>
                </ul>
            </section>
        </div>

        <Modal :show="confirmingPurge !== null" @close="confirmingPurge = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delete {{ confirmingPurge?.label }} permanently?</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">This action cannot be undone.</p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="confirmingPurge = null">Cancel</SecondaryButton>
                    <DangerButton type="button" @click="confirmPurge">Delete permanently</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
