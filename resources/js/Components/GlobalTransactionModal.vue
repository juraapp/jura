<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalForm from '@/Components/ModalForm.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useTransactionModal } from '@/composables/useTransactionModal';
import { store, update, formOptions, editData } from '@/actions/App/Http/Controllers/TransactionController';

const { isOpen, editingTransaction, defaultType, close } = useTransactionModal();

const activeTab = ref('expense');
const accounts = ref([]);
const expenseCategories = ref([]);
const incomeCategories = ref([]);
let optionsLoaded = false;

const form = useForm({
    account_id: '',
    category_id: '',
    from_account_id: '',
    to_account_id: '',
    amount: '',
    date: new Date().toISOString().slice(0, 10),
    description: '',
    notes: '',
    payment_method: '',
});

const tabs = [
    { value: 'expense', label: 'Expense' },
    { value: 'income', label: 'Income' },
    { value: 'transfer', label: 'Transfer' },
];

const categoriesForTab = computed(() => (activeTab.value === 'income' ? incomeCategories.value : expenseCategories.value));

const loadOptions = async () => {
    if (optionsLoaded) {
        return;
    }

    const response = await fetch(formOptions.url());
    const data = await response.json();
    accounts.value = data.accounts;
    expenseCategories.value = data.expenseCategories;
    incomeCategories.value = data.incomeCategories;
    optionsLoaded = true;
};

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.date = new Date().toISOString().slice(0, 10);
};

watch(isOpen, async (open) => {
    if (!open) {
        return;
    }

    await loadOptions();

    if (editingTransaction.value) {
        const response = await fetch(editData.url(editingTransaction.value));
        const data = await response.json();

        resetForm();
        activeTab.value = data.type;
        form.date = data.date;
        form.description = data.description ?? '';
        form.notes = data.notes ?? '';
        form.amount = String(data.amount);

        if (data.type === 'transfer') {
            form.from_account_id = data.from_account_id;
            form.to_account_id = data.to_account_id;
        } else {
            form.account_id = data.account_id;
            form.category_id = data.category_id;
            form.payment_method = data.payment_method ?? '';
        }
    } else {
        resetForm();
        activeTab.value = defaultType.value;
    }
});

const switchTab = (tab) => {
    activeTab.value = tab;
    form.category_id = '';
};

const submit = () => {
    const isTransfer = activeTab.value === 'transfer';

    form.transform((data) => (isTransfer
        ? {
            type: 'transfer',
            from_account_id: data.from_account_id,
            to_account_id: data.to_account_id,
            amount: data.amount,
            date: data.date,
            description: data.description,
            notes: data.notes,
        }
        : {
            type: activeTab.value,
            account_id: data.account_id,
            category_id: data.category_id,
            amount: data.amount,
            date: data.date,
            description: data.description,
            notes: data.notes,
            payment_method: data.payment_method || null,
        }));

    if (editingTransaction.value) {
        form.submit(update(editingTransaction.value), {
            preserveScroll: true,
            onSuccess: () => close(),
        });
    } else {
        form.submit(store(), {
            preserveScroll: true,
            onSuccess: () => close(),
        });
    }
};
</script>

<template>
    <ModalForm
        :show="isOpen"
        :title="editingTransaction ? 'Edit transaction' : 'New transaction'"
        @close="close"
    >
        <div v-if="!editingTransaction" class="-mt-2 mb-4 flex gap-1 border-b border-gray-200 dark:border-gray-700">
            <button
                v-for="tab in tabs"
                :key="tab.value"
                type="button"
                class="rounded-t-lg border-b-2 px-4 py-2 text-sm font-medium transition"
                :class="activeTab === tab.value
                    ? 'border-primary-600 text-primary-600 dark:text-primary-400'
                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                @click="switchTab(tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>

        <form id="transaction-form" class="space-y-4" @submit.prevent="submit">
            <template v-if="activeTab === 'transfer'">
                <div>
                    <InputLabel for="from_account_id" value="From account" />
                    <select
                        id="from_account_id"
                        v-model="form.from_account_id"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    >
                        <option value="">Select an account</option>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.from_account_id" />
                </div>

                <div>
                    <InputLabel for="to_account_id" value="To account" />
                    <select
                        id="to_account_id"
                        v-model="form.to_account_id"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    >
                        <option value="">Select an account</option>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.to_account_id" />
                </div>
            </template>

            <template v-else>
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
                        <option v-for="category in categoriesForTab" :key="category.id" :value="category.id">{{ category.name }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.category_id" />
                </div>
            </template>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <InputLabel for="amount" value="Amount" />
                    <TextInput id="amount" v-model="form.amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.amount" />
                </div>

                <div>
                    <InputLabel for="date" value="Date" />
                    <TextInput id="date" v-model="form.date" type="date" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.date" />
                </div>
            </div>

            <div v-if="activeTab === 'expense'">
                <InputLabel for="payment_method" value="Payment method" />
                <select
                    id="payment_method"
                    v-model="form.payment_method"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                >
                    <option value="">Unspecified</option>
                    <option value="cash">Cash</option>
                    <option value="debit_card">Debit card</option>
                    <option value="credit_card">Credit card</option>
                    <option value="bank_transfer">Bank transfer</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div>
                <InputLabel for="description" value="Description" />
                <TextInput id="description" v-model="form.description" type="text" class="mt-1 block w-full" />
                <InputError class="mt-1" :message="form.errors.description" />
            </div>

            <div>
                <InputLabel for="notes" value="Notes" />
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="2"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                ></textarea>
                <InputError class="mt-1" :message="form.errors.notes" />
            </div>
        </form>

        <template #footer>
            <SecondaryButton type="button" @click="close">Cancel</SecondaryButton>
            <PrimaryButton form="transaction-form" type="submit" :disabled="form.processing">Save</PrimaryButton>
        </template>
    </ModalForm>
</template>
