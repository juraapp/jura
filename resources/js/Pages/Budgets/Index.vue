<script setup>
import { ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
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
import { store, update, destroy } from '@/actions/App/Http/Controllers/BudgetController';

defineProps({
    budgets: { type: Array, required: true },
    expenseCategories: { type: Array, required: true },
    periodTypes: { type: Array, required: true },
    currencies: { type: Array, required: true },
});

const page = usePage();
const showModal = ref(false);
const editingBudget = ref(null);
const confirmingDeleteBudget = ref(null);

const form = useForm({
    category_id: '',
    currency: page.props.auth.user?.currency_default ?? 'COP',
    amount: '',
    period_type: 'monthly',
});

const openCreateModal = () => {
    editingBudget.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (budget) => {
    editingBudget.value = budget;
    form.clearErrors();
    form.category_id = budget.category.id;
    form.currency = budget.currency;
    form.amount = String(budget.amount);
    form.period_type = budget.periodType;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const submit = () => {
    if (editingBudget.value) {
        form.submit(update(editingBudget.value.id), {
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

const confirmDelete = (budget) => {
    confirmingDeleteBudget.value = budget;
};

const deleteBudget = () => {
    router.delete(destroy.url(confirmingDeleteBudget.value.id), {
        preserveScroll: true,
        onFinish: () => (confirmingDeleteBudget.value = null),
    });
};

const barColor = (status) => ({
    exceeded: 'bg-red-500',
    near_limit: 'bg-orange-400',
}[status] ?? 'bg-green-500');
</script>

<template>
    <Head title="Budgets" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Budgets
                </h2>
                <PrimaryButton type="button" @click="openCreateModal">
                    <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                    New budget
                </PrimaryButton>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <EmptyState
                v-if="budgets.length === 0"
                icon="chart-pie"
                title="You don't have any budgets yet"
                description="Set a spending limit per category for this period and we'll warn you as you approach it."
            >
                <template #action>
                    <PrimaryButton type="button" @click="openCreateModal">
                        <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                        Create my first budget
                    </PrimaryButton>
                </template>
            </EmptyState>

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div
                    v-for="budget in budgets"
                    :key="budget.id"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <CategoryIcon :category="budget.category" />
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">{{ budget.category.name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ budget.periodTypeLabel }} &middot; {{ budget.currency }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                                @click="openEditModal(budget)"
                            >
                                <DynamicIcon name="pencil" class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                @click="confirmDelete(budget)"
                            >
                                <DynamicIcon name="trash" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="mb-1 flex items-baseline justify-between text-sm">
                            <span class="font-semibold text-gray-900 dark:text-white">
                                <Money :amount="budget.spent" :currency="budget.currency" />
                            </span>
                            <span class="text-gray-400">
                                of <Money :amount="budget.amount" :currency="budget.currency" />
                            </span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                            <div class="h-full transition-all" :class="barColor(budget.status)" :style="{ width: Math.min(budget.percentage, 100) + '%' }"></div>
                        </div>
                        <div class="mt-1.5 flex items-center justify-between text-xs">
                            <span class="text-gray-400">{{ budget.percentage.toFixed(0) }}% used</span>
                            <span v-if="budget.status === 'exceeded'" class="font-medium text-red-600 dark:text-red-400">Budget exceeded</span>
                            <span v-else-if="budget.status === 'near_limit'" class="font-medium text-orange-500">Near limit</span>
                            <span v-else class="text-gray-400">
                                <Money :amount="budget.remaining" :currency="budget.currency" /> available
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ModalForm
            :show="showModal"
            :title="editingBudget ? 'Edit budget' : 'New budget'"
            @close="closeModal"
        >
            <form id="budget-form" class="space-y-4" @submit.prevent="submit">
                <div>
                    <InputLabel for="category_id" value="Category" />
                    <select
                        id="category_id"
                        v-model="form.category_id"
                        :disabled="editingBudget !== null"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:disabled:bg-gray-800"
                    >
                        <option value="">Select a category</option>
                        <option v-for="category in expenseCategories" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.category_id" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="amount" value="Limit amount" />
                        <TextInput id="amount" v-model="form.amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="form.errors.amount" />
                    </div>
                    <div>
                        <InputLabel for="currency" value="Currency" />
                        <select
                            id="currency"
                            v-model="form.currency"
                            :disabled="editingBudget !== null"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:disabled:bg-gray-800"
                        >
                            <option v-for="currencyOption in currencies" :key="currencyOption" :value="currencyOption">
                                {{ currencyOption }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.currency" />
                    </div>
                </div>

                <div>
                    <InputLabel for="period_type" value="Period" />
                    <select
                        id="period_type"
                        v-model="form.period_type"
                        :disabled="editingBudget !== null"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:disabled:bg-gray-800"
                    >
                        <option v-for="periodType in periodTypes" :key="periodType.value" :value="periodType.value">
                            {{ periodType.label }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Applies to the current period (this month or this year).</p>
                </div>
            </form>

            <template #footer>
                <SecondaryButton type="button" @click="closeModal">Cancel</SecondaryButton>
                <PrimaryButton form="budget-form" type="submit" :disabled="form.processing">Save</PrimaryButton>
            </template>
        </ModalForm>

        <Modal :show="confirmingDeleteBudget !== null" @close="confirmingDeleteBudget = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delete this budget?</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    This action cannot be undone.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="confirmingDeleteBudget = null">Cancel</SecondaryButton>
                    <DangerButton type="button" @click="deleteBudget">Delete</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
