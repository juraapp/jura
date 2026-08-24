<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CategoryIcon from '@/Components/CategoryIcon.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Money from '@/Components/Money.vue';
import ModalForm from '@/Components/ModalForm.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { store, update, destroy, toggleActive } from '@/actions/App/Http/Controllers/RecurringTransactionController';

const props = defineProps({
    recurrences: { type: Array, required: true },
    accounts: { type: Array, required: true },
    expenseCategories: { type: Array, required: true },
    incomeCategories: { type: Array, required: true },
    frequencies: { type: Array, required: true },
});

const showModal = ref(false);
const editingRecurring = ref(null);
const confirmingDeleteRecurring = ref(null);

const form = useForm({
    type: 'expense',
    account_id: '',
    category_id: '',
    amount: '',
    description: '',
    frequency: 'monthly',
    next_due_date: new Date().toISOString().slice(0, 10),
    end_date: '',
});

const categoriesForType = computed(() => (form.type === 'income' ? props.incomeCategories : props.expenseCategories));

const openCreateModal = () => {
    editingRecurring.value = null;
    form.reset();
    form.clearErrors();
    form.next_due_date = new Date().toISOString().slice(0, 10);
    showModal.value = true;
};

const openEditModal = (recurring) => {
    editingRecurring.value = recurring;
    form.clearErrors();
    form.type = recurring.type;
    form.account_id = recurring.account.id;
    form.category_id = recurring.category.id;
    form.amount = String(recurring.amount);
    form.description = recurring.description ?? '';
    form.frequency = recurring.frequency;
    form.next_due_date = recurring.nextDueDate;
    form.end_date = recurring.endDate ?? '';
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const submit = () => {
    if (editingRecurring.value) {
        form.submit(update(editingRecurring.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.submit(store(), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

const toggle = (recurring) => {
    router.patch(toggleActive.url(recurring.id), {}, { preserveScroll: true });
};

const confirmDelete = (recurring) => {
    confirmingDeleteRecurring.value = recurring;
};

const deleteRecurring = () => {
    router.delete(destroy.url(confirmingDeleteRecurring.value.id), {
        preserveScroll: true,
        onFinish: () => (confirmingDeleteRecurring.value = null),
    });
};

const formatDate = (date) => new Date(date).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <Head title="Recurring transactions" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Recurring transactions
                </h2>
                <PrimaryButton type="button" @click="openCreateModal">
                    <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                    New recurring
                </PrimaryButton>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <EmptyState
                v-if="recurrences.length === 0"
                icon="arrow-path"
                title="Track your recurring payments"
                description="Netflix, rent, utilities... register them once and we'll generate them automatically as they come due."
            >
                <template #action>
                    <PrimaryButton type="button" @click="openCreateModal">
                        <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                        Create my first recurring
                    </PrimaryButton>
                </template>
            </EmptyState>

            <div v-else class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Description</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Frequency</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Next payment</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Amount</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr
                            v-for="recurring in recurrences"
                            :key="recurring.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/40"
                            :class="{ 'opacity-50': !recurring.isActive }"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <CategoryIcon :category="recurring.category" size="h-7 w-7" />
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ recurring.description || recurring.category.name }}</p>
                                        <p class="text-xs text-gray-400">{{ recurring.account.name }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ recurring.frequencyLabel }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ formatDate(recurring.nextDueDate) }}</td>
                            <td
                                class="px-4 py-3 text-right text-sm font-semibold tabular-nums"
                                :class="recurring.type === 'income' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                            >
                                <Money :amount="recurring.amount" :currency="recurring.account.currency" />
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button
                                        type="button"
                                        class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                                        :title="recurring.isActive ? 'Pause' : 'Reactivate'"
                                        @click="toggle(recurring)"
                                    >
                                        <DynamicIcon :name="recurring.isActive ? 'check-circle' : 'arrow-path'" class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                                        @click="openEditModal(recurring)"
                                    >
                                        <DynamicIcon name="pencil" class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                        @click="confirmDelete(recurring)"
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

        <ModalForm
            :show="showModal"
            :title="editingRecurring ? 'Edit recurring' : 'New recurring'"
            @close="closeModal"
        >
            <form id="recurring-form" class="space-y-4" @submit.prevent="submit">
                <div class="flex gap-2">
                    <button
                        v-for="option in [{ value: 'expense', label: 'Expense' }, { value: 'income', label: 'Income' }]"
                        :key="option.value"
                        type="button"
                        class="flex-1 rounded-lg border px-3 py-2 text-sm font-medium"
                        :class="form.type === option.value
                            ? 'border-primary-600 bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400'
                            : 'border-gray-300 text-gray-600 dark:border-gray-600 dark:text-gray-300'"
                        @click="form.type = option.value; form.category_id = ''"
                    >
                        {{ option.label }}
                    </button>
                </div>

                <div>
                    <InputLabel for="account_id" value="Account" />
                    <select
                        id="account_id"
                        v-model="form.account_id"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    >
                        <option value="">Select an account</option>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.account_id" />
                </div>

                <div>
                    <InputLabel for="category_id" value="Category" />
                    <select
                        id="category_id"
                        v-model="form.category_id"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    >
                        <option value="">Select a category</option>
                        <option v-for="category in categoriesForType" :key="category.id" :value="category.id">{{ category.name }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.category_id" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="amount" value="Amount" />
                        <TextInput id="amount" v-model="form.amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="form.errors.amount" />
                    </div>
                    <div>
                        <InputLabel for="frequency" value="Frequency" />
                        <select
                            id="frequency"
                            v-model="form.frequency"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option v-for="frequency in frequencies" :key="frequency.value" :value="frequency.value">{{ frequency.label }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="next_due_date" value="Next payment" />
                        <TextInput id="next_due_date" v-model="form.next_due_date" type="date" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="form.errors.next_due_date" />
                    </div>
                    <div>
                        <InputLabel for="end_date" value="Ends (optional)" />
                        <TextInput id="end_date" v-model="form.end_date" type="date" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="form.errors.end_date" />
                    </div>
                </div>

                <div>
                    <InputLabel for="description" value="Description" />
                    <TextInput id="description" v-model="form.description" type="text" class="mt-1 block w-full" placeholder="Netflix, Rent..." />
                </div>
            </form>

            <template #footer>
                <SecondaryButton type="button" @click="closeModal">Cancel</SecondaryButton>
                <PrimaryButton form="recurring-form" type="submit" :disabled="form.processing">Save</PrimaryButton>
            </template>
        </ModalForm>

        <Modal :show="confirmingDeleteRecurring !== null" @close="confirmingDeleteRecurring = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delete this recurring transaction?</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    This action cannot be undone.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="confirmingDeleteRecurring = null">Cancel</SecondaryButton>
                    <DangerButton type="button" @click="deleteRecurring">Delete</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
