<script setup>
import { useI18n } from 'vue-i18n';
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalForm from '@/Components/ModalForm.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useTransactionModal } from '@/composables/useTransactionModal';
import { useToast } from '@/composables/useToast';
import { useToday } from '@/composables/useToday';
import { useFormat } from '@/composables/useFormat';
import { store, update, formOptions, editData } from '@/actions/App/Modules/Ledger/Http/Controllers/TransactionController';
import { spendOptions } from '@/actions/App/Modules/Planning/Http/Controllers/BucketController';

const todayDate = useToday();
const format = useFormat();

const { t } = useI18n();

const { isOpen, editingTransaction, defaultType, close } = useTransactionModal();
const { push: pushToast } = useToast();

const activeTab = ref('expense');
const activeAccounts = ref([]);
/** Accounts of the transaction being edited, so an inactive one still shows its name instead of "Select account". */
const editAccounts = ref([]);
const loading = ref(false);
let openRequest = 0;
const expenseCategories = ref([]);
const incomeCategories = ref([]);
let optionsLoaded = false;
/** Buckets still taking money, fetched on every open since their funds change all the time. */
const buckets = ref([]);

const form = useForm({
    account_id: '',
    category_id: '',
    from_account_id: '',
    to_account_id: '',
    amount: '',
    date: todayDate(),
    time: '',
    description: '',
    notes: '',
    payment_method: '',
    bucket_id: '',
});

const tabs = computed(() => [
    { value: 'expense', label: t('ui.transaction_modal.expense') },
    { value: 'income', label: t('ui.transaction_modal.income') },
    { value: 'transfer', label: t('ui.transaction_modal.transfer') },
]);

const accounts = computed(() => [
    ...activeAccounts.value,
    ...editAccounts.value.filter((account) => !activeAccounts.value.some((active) => active.id === account.id)),
]);

const fetchJson = async (url) => {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
};

const selectedAccount = computed(() => accounts.value.find((account) => account.id === Number(form.account_id)) ?? null);
/** Buckets an expense in the chosen account can be paid from: same currency, no tracked category, account on budget. */
const spendableBuckets = computed(() => {
    const account = selectedAccount.value;
    if (activeTab.value !== 'expense' || !account || account.on_budget === false) {
        return [];
    }

    return buckets.value.filter((bucket) => bucket.categoryId === null && bucket.currency === account.currency);
});
/** A funded spending bucket that already counts the chosen category. */
const trackingBucket = computed(() => {
    if (activeTab.value !== 'expense' || !form.category_id || !selectedAccount.value) {
        return null;
    }

    return buckets.value.find((bucket) => bucket.funded && bucket.categoryId === Number(form.category_id) && bucket.currency === selectedAccount.value.currency) ?? null;
});

watch(spendableBuckets, (choices) => {
    if (form.bucket_id && !choices.some((bucket) => bucket.id === Number(form.bucket_id))) {
        form.bucket_id = '';
    }
});

const categoriesForTab = computed(() => (activeTab.value === 'income' ? incomeCategories.value : expenseCategories.value));

const loadOptions = async () => {
    if (optionsLoaded) {
        return;
    }

    const data = await fetchJson(formOptions.url());
    activeAccounts.value = data.accounts;
    expenseCategories.value = data.expenseCategories;
    incomeCategories.value = data.incomeCategories;
    optionsLoaded = true;
};

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.date = todayDate();
    form.time = new Date().toTimeString().slice(0, 5);
};

watch(isOpen, async (open) => {
    if (!open) {
        /* A request still in flight belongs to a modal the user closed: it must neither fill the form nor toast. */
        openRequest++;
        loading.value = false;

        return;
    }

    const request = ++openRequest;
    resetForm();
    editAccounts.value = [];
    activeTab.value = editingTransaction.value ? activeTab.value : defaultType.value;
    loading.value = true;

    try {
        await loadOptions();
        const bucketData = await fetchJson(spendOptions.url());
        if (request !== openRequest) {
            return;
        }
        buckets.value = bucketData.buckets;

        if (!editingTransaction.value) {
            return;
        }

        const data = await fetchJson(editData.url(editingTransaction.value));
        if (request !== openRequest) {
            return;
        }

        activeTab.value = data.type;
        editAccounts.value = data.accounts ?? [];
        form.date = data.date;
        form.time = data.time ?? '';
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
            form.bucket_id = data.bucket_id ?? '';
        }
    } catch {
        if (request === openRequest) {
            pushToast({ type: 'error', message: t('ui.common.request_failed') });
            close();
        }
    } finally {
        if (request === openRequest) {
            loading.value = false;
        }
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
            time: data.time || null,
            description: data.description,
            notes: data.notes,
        }
        : {
            type: activeTab.value,
            account_id: data.account_id,
            category_id: data.category_id,
            amount: data.amount,
            date: data.date,
            time: data.time || null,
            description: data.description,
            notes: data.notes,
            payment_method: data.payment_method || null,
            ...(activeTab.value === 'expense' ? { bucket_id: data.bucket_id || null } : {}),
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
        :title="$t(editingTransaction ? 'ui.transaction_modal.edit' : 'ui.transaction_modal.new')"
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

        <div v-if="loading && editingTransaction" class="py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ $t('ui.common.loading') }}</div>

        <form v-show="!(loading && editingTransaction)" id="transaction-form" class="space-y-4" @submit.prevent="submit">
            <template v-if="activeTab === 'transfer'">
                <div>
                    <InputLabel for="from_account_id" :value="$t('ui.transaction_modal.from_account')" />
                    <select
                        id="from_account_id"
                        v-model="form.from_account_id"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    >
                        <option value="">{{ $t('ui.transaction_modal.select_account') }}</option>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }} · {{ account.currency }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.from_account_id" />
                </div>

                <div>
                    <InputLabel for="to_account_id" :value="$t('ui.transaction_modal.to_account')" />
                    <select
                        id="to_account_id"
                        v-model="form.to_account_id"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    >
                        <option value="">{{ $t('ui.transaction_modal.select_account') }}</option>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }} · {{ account.currency }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.to_account_id" />
                </div>
            </template>

            <template v-else>
                <div>
                    <InputLabel for="account_id" :value="$t('ui.transaction_modal.account')" />
                    <select
                        id="account_id"
                        v-model="form.account_id"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    >
                        <option value="">{{ $t('ui.transaction_modal.select_account') }}</option>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }} · {{ account.currency }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.account_id" />
                </div>

                <div>
                    <InputLabel for="category_id" :value="$t('ui.transaction_modal.category')" />
                    <select
                        id="category_id"
                        v-model="form.category_id"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    >
                        <option value="">{{ $t('ui.transaction_modal.select_category') }}</option>
                        <option v-for="category in categoriesForTab" :key="category.id" :value="category.id">{{ category.name }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.category_id" />
                </div>
            </template>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <InputLabel for="amount" :value="$t('ui.transaction_modal.amount')" />
                    <TextInput id="amount" v-model="form.amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.amount" />
                </div>

                <div>
                    <InputLabel for="date" :value="$t('ui.transaction_modal.date')" />
                    <TextInput id="date" v-model="form.date" type="date" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.date" />
                </div>
                <div>
                    <InputLabel for="time" :value="$t('ui.transaction_modal.time')" />
                    <TextInput id="time" v-model="form.time" type="time" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.time" />
                </div>
            </div>

            <div v-if="activeTab === 'expense'">
                <InputLabel for="payment_method" :value="$t('ui.transaction_modal.payment_method')" />
                <select
                    id="payment_method"
                    v-model="form.payment_method"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                >
                    <option value="">{{ $t('ui.transaction_modal.unspecified') }}</option>
                    <option value="cash">{{ $t('ui.transaction_modal.cash') }}</option>
                    <option value="debit_card">{{ $t('ui.transaction_modal.debit_card') }}</option>
                    <option value="credit_card">{{ $t('ui.transaction_modal.credit_card') }}</option>
                    <option value="bank_transfer">{{ $t('ui.transaction_modal.bank_transfer') }}</option>
                    <option value="other">{{ $t('ui.transaction_modal.other') }}</option>
                </select>
            </div>

            <div v-if="spendableBuckets.length || form.errors.bucket_id">
                <InputLabel for="bucket_id" :value="$t('ui.transaction_modal.pay_from_bucket')" />
                <select
                    id="bucket_id"
                    v-model="form.bucket_id"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                >
                    <option value="">{{ $t('ui.transaction_modal.no_bucket') }}</option>
                    <option v-for="bucket in spendableBuckets" :key="bucket.id" :value="bucket.id">{{ bucket.name }} · {{ format.money(bucket.available, bucket.currency) }}</option>
                </select>
                <p v-if="trackingBucket && !form.bucket_id" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.transaction_modal.counted_in_bucket', { name: trackingBucket.name }) }}</p>
                <p v-else-if="trackingBucket" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.transaction_modal.paid_instead', { name: trackingBucket.name }) }}</p>
                <InputError class="mt-1" :message="form.errors.bucket_id" />
            </div>
            <p v-else-if="trackingBucket" class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.transaction_modal.counted_in_bucket', { name: trackingBucket.name }) }}</p>

            <div>
                <InputLabel for="description" :value="$t('ui.transaction_modal.description')" />
                <TextInput id="description" v-model="form.description" type="text" class="mt-1 block w-full" />
                <InputError class="mt-1" :message="form.errors.description" />
            </div>

            <div>
                <InputLabel for="notes" :value="$t('ui.transaction_modal.notes')" />
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
            <SecondaryButton type="button" @click="close">{{ $t('ui.common.cancel') }}</SecondaryButton>
            <PrimaryButton form="transaction-form" type="submit" :disabled="form.processing || loading">{{ $t('ui.transaction_modal.save') }}</PrimaryButton>
        </template>
    </ModalForm>
</template>
