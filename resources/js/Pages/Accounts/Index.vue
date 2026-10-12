<script setup>
import { ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Money from '@/Components/Money.vue';
import ModalForm from '@/Components/ModalForm.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { store, update, destroy } from '@/actions/App/Modules/Ledger/Http/Controllers/AccountController';

defineProps({
    accounts: { type: Array, required: true },
    accountTypes: { type: Array, required: true },
    yieldCompoundings: { type: Array, required: true },
});

const page = usePage();
const showModal = ref(false);
const editingAccount = ref(null);
const confirmingDeleteAccount = ref(null);

const form = useForm({
    name: '',
    type: 'bank',
    institution: '',
    initial_balance: '0',
    yield_rate: '',
    yield_compounding: 'daily',
    currency: page.props.auth.user?.currency_default ?? 'COP',
    color: '#2563eb',
    masked_number: '',
    is_active: true,
    notes: '',
});

const openCreateModal = () => {
    editingAccount.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (account) => {
    editingAccount.value = account;
    form.clearErrors();
    form.name = account.name;
    form.type = account.type;
    form.institution = account.institution ?? '';
    form.initial_balance = String(account.initial_balance);
    form.yield_rate = account.yield_rate === null ? '' : String(account.yield_rate);
    form.yield_compounding = account.yield_compounding;
    form.currency = account.currency;
    form.color = account.color;
    form.masked_number = account.masked_number ?? '';
    form.is_active = account.is_active;
    form.notes = account.notes ?? '';
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const submit = () => {
    if (editingAccount.value) {
        form.submit(update(editingAccount.value.id), {
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

const confirmDelete = (account) => {
    confirmingDeleteAccount.value = account;
};

const deleteAccount = () => {
    router.delete(destroy.url(confirmingDeleteAccount.value.id), {
        preserveScroll: true,
        onFinish: () => (confirmingDeleteAccount.value = null),
    });
};
</script>

<template>
    <Head :title="$t('ui.accounts.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ $t('ui.accounts.title') }}
                </h2>
                <PrimaryButton type="button" @click="openCreateModal">
                    <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                    {{ $t('ui.accounts.new') }}
                </PrimaryButton>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <EmptyState
                v-if="accounts.length === 0"
                icon="wallet"
                :title="$t('ui.accounts.empty_title')"
                :description="$t('ui.accounts.empty_hint')"
            >
                <template #action>
                    <PrimaryButton type="button" @click="openCreateModal">
                        <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                        {{ $t('ui.accounts.create_first') }}
                    </PrimaryButton>
                </template>
            </EmptyState>

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="account in accounts"
                    :key="account.id"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    :class="{ 'opacity-60': !account.is_active }"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-10 w-10 items-center justify-center rounded-full"
                                :style="{ backgroundColor: account.color + '1a', color: account.color }"
                            >
                                <DynamicIcon :name="account.icon" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">{{ account.name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ account.typeLabel }}
                                    <template v-if="account.masked_number"> &middot; {{ account.masked_number }}</template>
                                    <template v-if="!account.is_active"> &middot; {{ $t('ui.accounts.archived') }}</template>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                                @click="openEditModal(account)"
                            >
                                <DynamicIcon name="pencil" class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                @click="confirmDelete(account)"
                            >
                                <DynamicIcon name="trash" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <p class="mt-4 text-2xl font-semibold text-gray-900 dark:text-white">
                        <Money :amount="account.balance" :currency="account.currency" />
                    </p>
                </div>
            </div>
        </div>

        <ModalForm
            :show="showModal"
            :title="editingAccount ? $t('ui.accounts.edit') : $t('ui.accounts.new')"
            @close="closeModal"
        >
            <form id="account-form" class="space-y-4" @submit.prevent="submit">
                <div>
                    <InputLabel for="name" :value="$t('ui.accounts.name')" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" :placeholder="$t('ui.accounts.name_placeholder')" />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="type" :value="$t('ui.accounts.type')" />
                        <select
                            id="type"
                            v-model="form.type"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option v-for="accountType in accountTypes" :key="accountType.value" :value="accountType.value">
                                {{ accountType.label }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.type" />
                    </div>

                    <div>
                        <InputLabel for="currency" :value="$t('ui.accounts.currency')" />
                        <TextInput id="currency" v-model="form.currency" type="text" maxlength="3" class="mt-1 block w-full uppercase" />
                        <InputError class="mt-1" :message="form.errors.currency" />
                    </div>
                </div>

                <div>
                    <InputLabel for="institution" :value="$t('ui.accounts.institution')" />
                    <TextInput id="institution" v-model="form.institution" type="text" class="mt-1 block w-full" :placeholder="$t('ui.accounts.institution_placeholder')" />
                    <InputError class="mt-1" :message="form.errors.institution" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="initial_balance" :value="$t('ui.accounts.initial_balance')" />
                        <TextInput id="initial_balance" v-model="form.initial_balance" type="number" step="0.01" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="form.errors.initial_balance" />
                    </div>

                    <div>
                        <InputLabel for="color" :value="$t('ui.accounts.color')" />
                        <input id="color" v-model="form.color" type="color" class="mt-1 block h-10 w-full rounded-lg border-gray-300 dark:border-gray-600" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="yield_rate" :value="$t('ui.accounts.yield_rate')" />
                        <TextInput id="yield_rate" v-model="form.yield_rate" type="number" step="0.0001" min="0" class="mt-1 block w-full" placeholder="9.3" />
                        <InputError class="mt-1" :message="form.errors.yield_rate" />
                    </div>
                    <div>
                        <InputLabel for="yield_compounding" :value="$t('ui.accounts.yield_compounding')" />
                        <SelectInput id="yield_compounding" v-model="form.yield_compounding">
                            <option v-for="option in yieldCompoundings" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </SelectInput>
                    </div>
                </div>

                <div>
                    <InputLabel for="masked_number" :value="$t('ui.accounts.masked_number')" />
                    <TextInput id="masked_number" v-model="form.masked_number" type="text" class="mt-1 block w-full" placeholder="****1234" />
                    <InputError class="mt-1" :message="form.errors.masked_number" />
                </div>

                <div>
                    <InputLabel for="notes" :value="$t('ui.accounts.notes')" />
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="2"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    ></textarea>
                    <InputError class="mt-1" :message="form.errors.notes" />
                </div>

                <label class="flex items-center gap-2">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600">
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $t('ui.accounts.active') }}</span>
                </label>
            </form>

            <template #footer>
                <SecondaryButton type="button" @click="closeModal">{{ $t('ui.common.cancel') }}</SecondaryButton>
                <PrimaryButton form="account-form" type="submit" :disabled="form.processing">{{ $t('ui.accounts.save') }}</PrimaryButton>
            </template>
        </ModalForm>

        <Modal :show="confirmingDeleteAccount !== null" @close="confirmingDeleteAccount = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $t('ui.accounts.delete_title') }}</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ $t('ui.accounts.delete_hint') }}
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="confirmingDeleteAccount = null">{{ $t('ui.common.cancel') }}</SecondaryButton>
                    <DangerButton type="button" @click="deleteAccount">{{ $t('ui.common.delete') }}</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
