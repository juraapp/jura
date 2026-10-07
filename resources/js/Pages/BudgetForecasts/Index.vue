<script setup>
import { ref, computed } from 'vue';
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
import { index as budgetsIndex } from '@/routes/budgets';
import { store, update, destroy } from '@/actions/App/Http/Controllers/BudgetForecastController';

const props = defineProps({
    budget: { type: Object, required: true },
    forecasts: { type: Array, required: true },
    categories: { type: Array, required: true },
    currencies: { type: Array, required: true },
});

const page = usePage();

const forecastForm = useForm({
    description: '',
    amount: '',
    date: new Date().toISOString().slice(0, 10),
    is_anticipated: true,
    currency: props.budget.currency,
    category_id: '',
    notes: '',
});

const allocateForm = useForm({
    allocated_amount: props.budget.allocatedAmount ?? '',
});

const showForecastModal = ref(false);
const editingForecast = ref(null);
const showAllocateModal = ref(false);
const confirmingDeleteForecast = ref(null);

const totalProjected = computed(() =>
    props.forecasts.filter(f => f.isAnticipated).reduce((sum, f) => sum + f.amount, 0)
);

const effectiveRemaining = computed(() =>
    props.budget.amount - totalProjected.value + (props.budget.allocatedAmount ?? 0)
);

const openCreateForecast = () => {
    editingForecast.value = null;
    forecastForm.reset();
    forecastForm.clearErrors();
    forecastForm.currency = props.budget.currency;
    showForecastModal.value = true;
};

const openEditForecast = (forecast) => {
    editingForecast.value = forecast;
    forecastForm.clearErrors();
    forecastForm.description = forecast.description;
    forecastForm.amount = String(forecast.amount);
    forecastForm.date = forecast.date;
    forecastForm.is_anticipated = forecast.isAnticipated;
    forecastForm.currency = forecast.currency;
    forecastForm.category_id = forecast.category?.id ?? '';
    forecastForm.notes = forecast.notes ?? '';
    showForecastModal.value = true;
};

const closeForecastModal = () => {
    showForecastModal.value = false;
};

const submitForecast = () => {
    if (editingForecast.value) {
        forecastForm.submit(update, {
            args: { budget: props.budget.id, budgetForecast: editingForecast.value.id },
            preserveScroll: true,
            onSuccess: () => closeForecastModal(),
        });
    } else {
        forecastForm.submit(store, {
            args: { budget: props.budget.id },
            preserveScroll: true,
            onSuccess: () => closeForecastModal(),
        });
    }
};

const confirmDelete = (forecast) => {
    confirmingDeleteForecast.value = forecast;
};

const deleteForecast = () => {
    router.delete(destroy.url({ budget: props.budget.id, budgetForecast: confirmingDeleteForecast.value.id }), {
        preserveScroll: true,
        onFinish: () => (confirmingDeleteForecast.value = null),
    });
};

const openAllocateModal = () => {
    allocateForm.allocated_amount = props.budget.allocatedAmount ?? '';
    allocateForm.clearErrors();
    showAllocateModal.value = true;
};

const closeAllocateModal = () => {
    showAllocateModal.value = false;
};

const submitAllocation = () => {
    router.patch(`/planning/budgets/${props.budget.id}`, {
        allocated_amount: allocateForm.allocated_amount ? parseFloat(allocateForm.allocated_amount) : null,
    }, {
        preserveScroll: true,
        onSuccess: (page) => {
            closeAllocateModal();
            if (page.props.flash?.success) {
                allocateForm.allocated_amount = props.budget.allocatedAmount ?? '';
            }
        },
    });
};
</script>

<template>
    <Head title="Projected expenses" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                        @click="router.visit(budgetsIndex().url)"
                    >
                        <DynamicIcon name="arrow-left" class="h-5 w-5" />
                    </button>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ budget.name }}
                    </h2>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Budget</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                        <Money :amount="budget.amount" :currency="budget.currency" />
                    </p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Allocated income</p>
                    <button
                        type="button"
                        class="mt-1 text-lg font-semibold text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400"
                        @click="openAllocateModal"
                    >
                        <Money :amount="budget.allocatedAmount ?? 0" :currency="budget.currency" />
                    </button>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Projected expenses</p>
                    <p class="mt-1 text-lg font-semibold text-orange-600 dark:text-orange-400">
                        <Money :amount="totalProjected" :currency="budget.currency" />
                    </p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Effective remaining</p>
                    <p class="mt-1 text-lg font-semibold" :class="effectiveRemaining >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                        <Money :amount="effectiveRemaining" :currency="budget.currency" />
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Projected expenses</h3>
                <PrimaryButton type="button" @click="openCreateForecast">
                    <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                    Add projected expense
                </PrimaryButton>
            </div>

            <EmptyState
                v-if="forecasts.length === 0"
                icon="clock"
                title="No projected expenses yet"
                description="Add known future expenses so you can see the full picture of your budget."
            >
                <template #action>
                    <PrimaryButton type="button" @click="openCreateForecast">
                        <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                        First projected expense
                    </PrimaryButton>
                </template>
            </EmptyState>

            <div v-else class="mt-4 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full table-auto text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Description</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Date</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Category</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Amount</th>
                            <th class="px-4 py-3 w-24"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr v-for="forecast in forecasts" :key="forecast.id" :class="forecast.isAnticipated ? '' : 'opacity-60'">
                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-900 dark:text-white">{{ forecast.description }}</span>
                                <p v-if="forecast.notes" class="text-xs text-gray-400">{{ forecast.notes }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ new Date(forecast.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                            </td>
                            <td class="px-4 py-3">
                                <template v-if="forecast.category">
                                    <CategoryIcon :category="forecast.category" size="sm" />
                                    <span class="ml-1 text-gray-600 dark:text-gray-300">{{ forecast.category.name }}</span>
                                </template>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">
                                <Money :amount="forecast.amount" :currency="forecast.currency" />
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <button
                                        type="button"
                                        class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                                        @click="openEditForecast(forecast)"
                                    >
                                        <DynamicIcon name="pencil" class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                        @click="confirmDelete(forecast)"
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

        <!-- Forecast Modal -->
        <ModalForm
            :show="showForecastModal"
            :title="editingForecast ? 'Edit projected expense' : 'New projected expense'"
            @close="closeForecastModal"
        >
            <form id="forecast-form" class="space-y-4" @submit.prevent="submitForecast">
                <div>
                    <InputLabel for="forecast_description" value="Description" />
                    <TextInput id="forecast_description" v-model="forecastForm.description" type="text" class="mt-1 block w-full" placeholder="Insurance premium" />
                    <InputError class="mt-1" :message="forecastForm.errors.description" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="forecast_amount" value="Amount" />
                        <TextInput id="forecast_amount" v-model="forecastForm.amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="forecastForm.errors.amount" />
                    </div>
                    <div>
                        <InputLabel for="forecast_date" value="Expected date" />
                        <TextInput id="forecast_date" v-model="forecastForm.date" type="date" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="forecastForm.errors.date" />
                    </div>
                </div>

                <div>
                    <InputLabel for="forecast_category" value="Category (optional)" />
                    <select
                        id="forecast_category"
                        v-model="forecastForm.category_id"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    >
                        <option value="">Select a category</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="forecastForm.errors.category_id" />
                </div>

                <div>
                    <InputLabel for="forecast_notes" value="Notes (optional)" />
                    <TextInput id="forecast_notes" v-model="forecastForm.notes" type="text" class="mt-1 block w-full" placeholder="Additional details" />
                    <InputError class="mt-1" :message="forecastForm.errors.notes" />
                </div>
            </form>

            <template #footer>
                <SecondaryButton type="button" @click="closeForecastModal">Cancel</SecondaryButton>
                <PrimaryButton form="forecast-form" type="submit" :disabled="forecastForm.processing">Save</PrimaryButton>
            </template>
        </ModalForm>

        <!-- Allocate Income Modal -->
        <ModalForm
            :show="showAllocateModal"
            title="Allocate income"
            @close="closeAllocateModal"
        >
            <form id="allocate-form" class="space-y-4" @submit.prevent="submitAllocation">
                <div>
                    <InputLabel for="allocated_amount" value="Planned income to allocate" />
                    <TextInput id="allocated_amount" v-model="allocateForm.allocated_amount" type="number" step="0.01" min="0" class="mt-1 block w-full" placeholder="Leave blank to allocate nothing" />
                    <InputError class="mt-1" :message="allocateForm.errors.allocated_amount" />
                    <p class="mt-1 text-xs text-gray-400">This income will be added back when calculating effective remaining.</p>
                </div>
            </form>

            <template #footer>
                <SecondaryButton type="button" @click="closeAllocateModal">Cancel</SecondaryButton>
                <PrimaryButton form="allocate-form" type="submit" :disabled="allocateForm.processing">Save</PrimaryButton>
            </template>
        </ModalForm>

        <!-- Delete Confirmation -->
        <Modal :show="confirmingDeleteForecast !== null" @close="confirmingDeleteForecast = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delete this projected expense?</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    This action cannot be undone.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="confirmingDeleteForecast = null">Cancel</SecondaryButton>
                    <DangerButton type="button" @click="deleteForecast">Delete</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
