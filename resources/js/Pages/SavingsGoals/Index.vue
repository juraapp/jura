<script setup>
import { ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
import { store, update, destroy, contribute, restore, forceDestroy, bulkDestroy } from '@/actions/App/Modules/Planning/Http/Controllers/SavingsGoalController';
import { useFormat } from '@/composables/useFormat';
import BulkCheckbox from '@/Components/BulkCheckbox.vue';
import BulkDeleteBar from '@/Components/BulkDeleteBar.vue';
import { useBulkSelection } from '@/composables/useBulkSelection';

const format = useFormat();

const props = defineProps({
    goals: { type: Array, required: true },
    currencies: { type: Array, required: true },
    iconChoices: { type: Array, required: true },
    trashedGoals: { type: Array, default: () => [] },
});

const bulk = useBulkSelection(() => props.goals);
watch(() => props.goals, () => bulk.clear());

const page = usePage();
const showModal = ref(false);
const editingGoal = ref(null);
const confirmingDeleteGoal = ref(null);
const confirmingPurgeGoal = ref(null);
const purgeGoal = () => {
    router.delete(forceDestroy.url(confirmingPurgeGoal.value.id), {
        preserveScroll: true,
        onFinish: () => (confirmingPurgeGoal.value = null),
    });
};
const showContributionModal = ref(false);
const contributingGoal = ref(null);

const form = useForm({
    name: '',
    target_amount: '',
    currency: page.props.auth.user?.currency_default ?? 'COP',
    target_date: '',
    icon: 'flag',
    color: '#2563eb',
});

const contributionForm = useForm({
    amount: '',
    date: new Date().toISOString().slice(0, 10),
});

const openCreateModal = () => {
    editingGoal.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (goal) => {
    editingGoal.value = goal;
    form.clearErrors();
    form.name = goal.name;
    form.target_amount = String(goal.targetAmount);
    form.currency = goal.currency;
    form.target_date = goal.targetDate ?? '';
    form.icon = goal.icon;
    form.color = goal.color;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const submit = () => {
    if (editingGoal.value) {
        form.submit(update(editingGoal.value.id), {
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

const confirmDelete = (goal) => {
    confirmingDeleteGoal.value = goal;
};

const deleteGoal = () => {
    router.delete(destroy.url(confirmingDeleteGoal.value.id), {
        preserveScroll: true,
        onFinish: () => (confirmingDeleteGoal.value = null),
    });
};

const openContributionModal = (goal) => {
    contributingGoal.value = goal;
    contributionForm.reset();
    contributionForm.date = new Date().toISOString().slice(0, 10);
    contributionForm.clearErrors();
    showContributionModal.value = true;
};

const closeContributionModal = () => {
    showContributionModal.value = false;
};

const submitContribution = () => {
    contributionForm.submit(contribute(contributingGoal.value.id), {
        preserveScroll: true,
        onSuccess: () => closeContributionModal(),
    });
};

const formatDate = (date) => format.date(date);
</script>

<template>
    <Head :title="$t('ui.savings_goals.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ $t('ui.savings_goals.title') }}
                </h2>
                <PrimaryButton type="button" @click="openCreateModal">
                    <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                    {{ $t('ui.savings_goals.new') }}
                </PrimaryButton>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <EmptyState
                v-if="goals.length === 0"
                icon="flag"
                :title="$t('ui.savings_goals.empty_title')"
                :description="$t('ui.savings_goals.empty_hint')"
            >
                <template #action>
                    <PrimaryButton type="button" @click="openCreateModal">
                        <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                        {{ $t('ui.savings_goals.create_first') }}
                    </PrimaryButton>
                </template>
            </EmptyState>

            <div v-else>
            <div class="mb-3 space-y-2">
                <label class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400"><BulkCheckbox :bulk="bulk" page /> {{ $t('ui.savings_goals.select_all') }}</label>
                <BulkDeleteBar :bulk="bulk" :url="bulkDestroy.url()" :noun="$t('ui.savings_goals.noun')" :note="$t('ui.savings_goals.bulk_note')" />
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div
                    v-for="goal in goals"
                    :key="goal.id"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-10 w-10 items-center justify-center rounded-full"
                                :style="{ backgroundColor: goal.color + '1a', color: goal.color }"
                            >
                                <DynamicIcon :name="goal.icon" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">{{ goal.name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <template v-if="goal.targetDate">{{ $t('ui.savings_goals.target', { date: formatDate(goal.targetDate) }) }} &middot; </template>
                                    {{ goal.currency }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                                @click="openEditModal(goal)"
                            >
                                <DynamicIcon name="pencil" class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                @click="confirmDelete(goal)"
                            >
                                <DynamicIcon name="trash" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="mb-1 flex items-baseline justify-between text-sm">
                            <span class="font-semibold text-gray-900 dark:text-white">
                                <Money :amount="goal.saved" :currency="goal.currency" />
                            </span>
                            <span class="text-gray-400">
                                {{ $t('ui.common.of') }} <Money :amount="goal.targetAmount" :currency="goal.currency" />
                            </span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                            <div class="h-full rounded-full bg-primary-600 transition-all" :style="{ width: goal.percentage + '%' }"></div>
                        </div>
                        <div class="mt-1.5 flex items-center justify-between text-xs">
                            <span class="text-gray-400">{{ format.number(goal.percentage, 0) }}%</span>
                            <span v-if="goal.status === 'completed'" class="font-medium text-green-600 dark:text-green-400">{{ $t('ui.savings_goals.completed') }}</span>
                            <span v-else-if="goal.schedule === 'behind'" class="font-medium text-orange-500">{{ $t('ui.savings_goals.behind') }}</span>
                            <span v-else-if="goal.schedule === 'ahead'" class="font-medium text-green-600 dark:text-green-400">{{ $t('ui.savings_goals.on_track') }}</span>
                        </div>

                        <p v-if="goal.suggestedMonthly" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ $t('ui.savings_goals.suggested') }}
                            <span class="font-medium text-gray-700 dark:text-gray-300">
                                <Money :amount="goal.suggestedMonthly" :currency="goal.currency" />
                            </span>
                        </p>
                    </div>

                    <button
                        v-if="goal.status !== 'completed'"
                        type="button"
                        class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-primary-200 bg-primary-50 px-3 py-1.5 text-xs font-medium text-primary-700 hover:bg-primary-100 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-400"
                        @click="openContributionModal(goal)"
                    >
                        <DynamicIcon name="plus" class="h-3.5 w-3.5" />
                        {{ $t('ui.savings_goals.contribute') }}
                    </button>
                </div>
            </div>
            </div>
                    <section v-if="trashedGoals.length" class="mt-8">
                <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $t('ui.savings_goals.deleted') }}</h3>
                <ul class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                    <li v-for="goal in trashedGoals" :key="goal.id" class="flex items-center gap-3 px-4 py-3">
                        <p class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900 dark:text-white">{{ goal.name }}</p>
                        <Money :amount="goal.targetAmount" :currency="goal.currency" class="text-sm text-gray-500" />
                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" :title="$t('ui.common.restore')" @click="router.post(restore.url(goal.id), {}, { preserveScroll: true })">
                            <DynamicIcon name="arrow-path" class="h-4 w-4" />
                        </button>
                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" :title="$t('ui.common.delete_permanently')" @click="confirmingPurgeGoal = goal">
                            <DynamicIcon name="trash" class="h-4 w-4" />
                        </button>
                    </li>
                </ul>
            </section>
        </div>

        <ModalForm
            :show="showModal"
            :title="editingGoal ? $t('ui.savings_goals.edit') : $t('ui.savings_goals.new')"
            @close="closeModal"
        >
            <form id="savings-goal-form" class="space-y-4" @submit.prevent="submit">
                <div>
                    <InputLabel for="name" :value="$t('ui.savings_goals.name')" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" :placeholder="$t('ui.savings_goals.name_placeholder')" />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="target_amount" :value="$t('ui.savings_goals.target_amount')" />
                        <TextInput id="target_amount" v-model="form.target_amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="form.errors.target_amount" />
                    </div>
                    <div>
                        <InputLabel for="goal_currency" :value="$t('ui.savings_goals.currency')" />
                        <select
                            id="goal_currency"
                            v-model="form.currency"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option v-for="currencyOption in currencies" :key="currencyOption" :value="currencyOption">
                                {{ currencyOption }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.currency" />
                    </div>
                </div>

                <div>
                    <InputLabel for="target_date" :value="$t('ui.savings_goals.target_date')" />
                    <TextInput id="target_date" v-model="form.target_date" type="date" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.target_date" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="icon" :value="$t('ui.savings_goals.icon')" />
                        <select
                            id="icon"
                            v-model="form.icon"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option v-for="choice in iconChoices" :key="choice" :value="choice">{{ choice }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.icon" />
                    </div>
                    <div>
                        <InputLabel for="color" :value="$t('ui.savings_goals.color')" />
                        <input id="color" v-model="form.color" type="color" class="mt-1 block h-10 w-full rounded-lg border-gray-300 dark:border-gray-600" />
                        <InputError class="mt-1" :message="form.errors.color" />
                    </div>
                </div>

                <div class="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-900/40">
                    <span
                        class="inline-flex h-8 w-8 items-center justify-center rounded-full"
                        :style="{ backgroundColor: form.color + '1a', color: form.color }"
                    >
                        <DynamicIcon :name="form.icon" class="h-4 w-4" />
                    </span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $t('ui.savings_goals.preview') }}</span>
                </div>
            </form>

            <template #footer>
                <SecondaryButton type="button" @click="closeModal">{{ $t('ui.common.cancel') }}</SecondaryButton>
                <PrimaryButton form="savings-goal-form" type="submit" :disabled="form.processing">{{ $t('ui.savings_goals.save') }}</PrimaryButton>
            </template>
        </ModalForm>

        <ModalForm
            :show="showContributionModal"
            :title="$t('ui.savings_goals.contribute')"
            @close="closeContributionModal"
        >
            <form id="contribution-form" class="space-y-4" @submit.prevent="submitContribution">
                <div>
                    <InputLabel for="contribution_amount" :value="$t('ui.savings_goals.amount')" />
                    <TextInput id="contribution_amount" v-model="contributionForm.amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="contributionForm.errors.amount" />
                </div>
                <div>
                    <InputLabel for="contribution_date" :value="$t('ui.savings_goals.date')" />
                    <TextInput id="contribution_date" v-model="contributionForm.date" type="date" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="contributionForm.errors.date" />
                </div>
            </form>

            <template #footer>
                <SecondaryButton type="button" @click="closeContributionModal">{{ $t('ui.common.cancel') }}</SecondaryButton>
                <PrimaryButton form="contribution-form" type="submit" :disabled="contributionForm.processing">{{ $t('ui.savings_goals.save') }}</PrimaryButton>
            </template>
        </ModalForm>

        <Modal :show="confirmingDeleteGoal !== null" @close="confirmingDeleteGoal = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $t('ui.savings_goals.delete_title') }}</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ $t('ui.common.cannot_be_undone') }}
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="confirmingDeleteGoal = null">{{ $t('ui.common.cancel') }}</SecondaryButton>
                    <DangerButton type="button" @click="deleteGoal">{{ $t('ui.common.delete') }}</DangerButton>
                </div>
            </div>
        </Modal>

        <Modal :show="confirmingPurgeGoal !== null" @close="confirmingPurgeGoal = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $t('ui.savings_goals.purge_title', { name: confirmingPurgeGoal?.name }) }}</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $t('ui.savings_goals.purge_hint') }}</p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="confirmingPurgeGoal = null">{{ $t('ui.common.cancel') }}</SecondaryButton>
                    <DangerButton type="button" @click="purgeGoal">{{ $t('ui.common.delete_permanently') }}</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
