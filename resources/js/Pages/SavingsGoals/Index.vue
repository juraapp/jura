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
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { store, update, destroy, contribute } from '@/actions/App/Http/Controllers/SavingsGoalController';

defineProps({
    goals: { type: Array, required: true },
    currencies: { type: Array, required: true },
    iconChoices: { type: Array, required: true },
});

const page = usePage();
const showModal = ref(false);
const editingGoal = ref(null);
const confirmingDeleteGoal = ref(null);
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

const formatDate = (date) => new Date(date).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <Head title="Savings goals" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Savings goals
                </h2>
                <PrimaryButton type="button" @click="openCreateModal">
                    <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                    New goal
                </PrimaryButton>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <EmptyState
                v-if="goals.length === 0"
                icon="flag"
                title="Set your first savings goal"
                description="Give it a name, a target amount, and a date, and we'll tell you how much to save each month."
            >
                <template #action>
                    <PrimaryButton type="button" @click="openCreateModal">
                        <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                        Create my first goal
                    </PrimaryButton>
                </template>
            </EmptyState>

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                                    <template v-if="goal.targetDate">Target: {{ formatDate(goal.targetDate) }} &middot; </template>
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
                                of <Money :amount="goal.targetAmount" :currency="goal.currency" />
                            </span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                            <div class="h-full rounded-full bg-primary-600 transition-all" :style="{ width: goal.percentage + '%' }"></div>
                        </div>
                        <div class="mt-1.5 flex items-center justify-between text-xs">
                            <span class="text-gray-400">{{ goal.percentage.toFixed(0) }}%</span>
                            <span v-if="goal.status === 'completed'" class="font-medium text-green-600 dark:text-green-400">Goal completed!</span>
                            <span v-else-if="goal.schedule === 'behind'" class="font-medium text-orange-500">Behind schedule</span>
                            <span v-else-if="goal.schedule === 'ahead'" class="font-medium text-green-600 dark:text-green-400">On track</span>
                        </div>

                        <p v-if="goal.suggestedMonthly" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Suggested monthly contribution:
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
                        Record contribution
                    </button>
                </div>
            </div>
        </div>

        <ModalForm
            :show="showModal"
            :title="editingGoal ? 'Edit goal' : 'New goal'"
            @close="closeModal"
        >
            <form id="savings-goal-form" class="space-y-4" @submit.prevent="submit">
                <div>
                    <InputLabel for="name" value="Name" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" placeholder="Buy a laptop" />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="target_amount" value="Target amount" />
                        <TextInput id="target_amount" v-model="form.target_amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="form.errors.target_amount" />
                    </div>
                    <div>
                        <InputLabel for="goal_currency" value="Currency" />
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
                    <InputLabel for="target_date" value="Target date (optional)" />
                    <TextInput id="target_date" v-model="form.target_date" type="date" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.target_date" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="icon" value="Icon" />
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
                        <InputLabel for="color" value="Color" />
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
                    <span class="text-sm text-gray-500 dark:text-gray-400">Preview</span>
                </div>
            </form>

            <template #footer>
                <SecondaryButton type="button" @click="closeModal">Cancel</SecondaryButton>
                <PrimaryButton form="savings-goal-form" type="submit" :disabled="form.processing">Save</PrimaryButton>
            </template>
        </ModalForm>

        <ModalForm
            :show="showContributionModal"
            title="Record contribution"
            @close="closeContributionModal"
        >
            <form id="contribution-form" class="space-y-4" @submit.prevent="submitContribution">
                <div>
                    <InputLabel for="contribution_amount" value="Amount" />
                    <TextInput id="contribution_amount" v-model="contributionForm.amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="contributionForm.errors.amount" />
                </div>
                <div>
                    <InputLabel for="contribution_date" value="Date" />
                    <TextInput id="contribution_date" v-model="contributionForm.date" type="date" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="contributionForm.errors.date" />
                </div>
            </form>

            <template #footer>
                <SecondaryButton type="button" @click="closeContributionModal">Cancel</SecondaryButton>
                <PrimaryButton form="contribution-form" type="submit" :disabled="contributionForm.processing">Save</PrimaryButton>
            </template>
        </ModalForm>

        <Modal :show="confirmingDeleteGoal !== null" @close="confirmingDeleteGoal = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delete this goal?</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    This action cannot be undone.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="confirmingDeleteGoal = null">Cancel</SecondaryButton>
                    <DangerButton type="button" @click="deleteGoal">Delete</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
