<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CategoryIcon from '@/Components/CategoryIcon.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Modal from '@/Components/Modal.vue';
import ModalForm from '@/Components/ModalForm.vue';
import Money from '@/Components/Money.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import SegmentedControl from '@/Components/SegmentedControl.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { useDarkMode } from '@/composables/useDarkMode';
import { CHART_PALETTE, formatCompact, formatMoney } from '@/chartPalette';
import {
    index as plannedEventsIndex,
    store,
    update,
    destroy,
    realize,
    skip,
    reopen,
    link,
    bulkDestroy,
} from '@/actions/App/Modules/Planning/Http/Controllers/PlannedEventController';
import { useFormat } from '@/composables/useFormat';
import BulkCheckbox from '@/Components/BulkCheckbox.vue';
import BulkDeleteBar from '@/Components/BulkDeleteBar.vue';
import { useBulkSelection } from '@/composables/useBulkSelection';

const format = useFormat();

const props = defineProps({
    events: { type: Array, required: true },
    projection: { type: Array, required: true },
    view: { type: String, required: true },
    views: { type: Array, required: true },
    months: { type: Number, required: true },
    accounts: { type: Array, required: true },
    expenseCategories: { type: Array, required: true },
    incomeCategories: { type: Array, required: true },
    frequencies: { type: Array, required: true },
    certainties: { type: Array, required: true },
    buckets: { type: Array, required: true },
});

const bulk = useBulkSelection(() => props.events);
watch(() => props.events, () => bulk.clear());

const page = usePage();
const { isDark } = useDarkMode();
const locale = () => page.props.auth.user?.locale ?? 'en';
const palette = () => CHART_PALETTE[isDark.value ? 'dark' : 'light'];
const today = new Date().toISOString().slice(0, 10);

const viewHints = {
    conservative: 'Only confirmed income counts. Expenses always count.',
    expected: 'Confirmed and expected income count.',
    optimistic: 'All planned income counts, even speculative.',
};

const changeView = (value) => {
    router.get(plannedEventsIndex.url({ query: { view: value, months: props.months } }), {}, { preserveScroll: true, preserveState: true });
};

const monthLabel = (month) => new Date(`${month}-01T00:00:00`).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
const shortMonth = (month) => new Date(`${month}-01T00:00:00`).toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
const formatDate = (date) => format.date(date, { year: false });

const groupedEvents = computed(() => {
    const groups = new Map();
    props.events.forEach((event) => {
        const key = event.date.slice(0, 7);
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(event);
    });

    return [...groups.entries()].sort(([a], [b]) => a.localeCompare(b)).map(([month, items]) => ({ month, items }));
});

const isOverdue = (event) => event.status === 'planned' && event.date < today;
const certaintyClass = (value) => ({
    confirmed: 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400',
    expected: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
    speculative: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
}[value]);

/* ---- projection chart ---- */
const chartOptions = (currency) => ({
    chart: { height: 280, fontFamily: 'Figtree, sans-serif', toolbar: { show: false }, stacked: false },
    colors: [palette().income, palette().expense, palette().savings],
    stroke: { width: [0, 0, 3], curve: 'smooth' },
    plotOptions: { bar: { columnWidth: '45%', borderRadius: 3 } },
    xaxis: { categories: currency.months.map((m) => shortMonth(m.month)), labels: { style: { colors: palette().text } } },
    yaxis: { labels: { style: { colors: palette().text }, formatter: (v) => formatCompact(v, locale()) } },
    grid: { borderColor: palette().grid },
    dataLabels: { enabled: false },
    legend: { labels: { colors: [palette().text, palette().text, palette().text], useSeriesColors: false } },
    tooltip: { y: { formatter: (v) => formatMoney(v, currency.currency, locale()) } },
});
const chartSeries = (currency) => [
    { name: 'Planned income', type: 'column', data: currency.months.map((m) => m.income) },
    { name: 'Planned expenses', type: 'column', data: currency.months.map((m) => m.expense) },
    { name: 'Projected balance', type: 'line', data: currency.months.map((m) => m.closing) },
];

/* ---- create / edit ---- */
const showModal = ref(false);
const editingEvent = ref(null);

const form = useForm({
    type: 'expense',
    description: '',
    amount: '',
    date: today,
    certainty: 'expected',
    account_id: '',
    category_id: '',
    notes: '',
    frequency: '',
    ends_on: '',
    scope: 'this',
    allocation_overrides: [],
});

// Per-bucket amounts for an income, edited as { [bucketId]: amount } and sent as a list.
const overrideAmounts = ref({});
form.transform((data) => ({
    ...data,
    allocation_overrides: data.type === 'income'
        ? Object.entries(overrideAmounts.value).filter(([, amount]) => Number(amount) > 0).map(([id, amount]) => ({ bucket_id: Number(id), amount }))
        : [],
}));

const categoriesForType = computed(() => (form.type === 'income' ? props.incomeCategories : props.expenseCategories));

const openCreateModal = (type = 'expense') => {
    editingEvent.value = null;
    form.reset();
    form.clearErrors();
    form.type = type;
    form.date = today;
    overrideAmounts.value = {};
    showModal.value = true;
};

const openEditModal = (event) => {
    editingEvent.value = event;
    form.clearErrors();
    form.type = event.type;
    form.description = event.description;
    form.amount = String(event.amount);
    form.date = event.date;
    form.certainty = event.certainty;
    form.account_id = event.account?.id ?? '';
    form.category_id = event.category?.id ?? '';
    form.notes = event.notes ?? '';
    form.frequency = '';
    form.ends_on = '';
    form.scope = 'this';
    overrideAmounts.value = Object.fromEntries((event.allocationOverrides ?? []).map((row) => [row.bucket_id, String(row.amount)]));
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const submit = () => {
    const action = editingEvent.value ? update(editingEvent.value.id) : store();
    form.submit(action, { preserveScroll: true, onSuccess: () => closeModal() });
};

/* ---- realize ---- */
const realizing = ref(null);
const realizeForm = useForm({ account_id: '', category_id: '', amount: '', date: today, description: '' });

const openRealize = (event) => {
    realizing.value = event;
    realizeForm.clearErrors();
    realizeForm.account_id = event.account?.id ?? (props.accounts.length === 1 ? props.accounts[0].id : '');
    realizeForm.category_id = event.category?.id ?? '';
    realizeForm.amount = String(event.amount);
    realizeForm.date = today;
    realizeForm.description = event.description;
};

const realizeCategories = computed(() => (realizing.value?.type === 'income' ? props.incomeCategories : props.expenseCategories));

const submitRealize = () => {
    realizeForm.submit(realize(realizing.value.id), { preserveScroll: true, onSuccess: () => (realizing.value = null) });
};

/* ---- skip / link / delete ---- */
const reopenEvent = (event) => router.post(reopen.url(event.id), {}, { preserveScroll: true });
const skipEvent = (event) => router.post(skip.url(event.id), {}, { preserveScroll: true });
const linkMatch = (event) => router.post(link.url(event.id), { transaction_id: event.suggestedMatch.id }, { preserveScroll: true });

const confirmingDelete = ref(null);
const deleteScope = ref('this');
const askDelete = (event) => {
    confirmingDelete.value = event;
    deleteScope.value = 'this';
};
const deleteEvent = () => {
    router.delete(destroy.url(confirmingDelete.value.id), {
        data: { scope: deleteScope.value },
        preserveScroll: true,
        onFinish: () => (confirmingDelete.value = null),
    });
};
</script>

<template>
    <Head title="Cash flow" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Cash flow</h2>
                <div class="flex items-center gap-2">
                    <SecondaryButton type="button" @click="openCreateModal('income')">
                        <DynamicIcon name="arrow-up-circle" class="mr-1.5 h-4 w-4" />
                        Expect income
                    </SecondaryButton>
                    <PrimaryButton type="button" @click="openCreateModal('expense')">
                        <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                        Plan an event
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <SegmentedControl :model-value="view" :options="views" @update:model-value="changeView" />
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ viewHints[view] }}</p>
                </div>
                <p class="text-xs text-gray-400">Projected figures are estimates. They never change your real balances.</p>
            </div>

            <section v-for="currency in projection" :key="currency.currency" class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Projected balance, {{ currency.currency }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Today: <Money :amount="currency.opening" :currency="currency.currency" />
                        </p>
                    </div>
                    <apexchart
                        :key="`cf-${currency.currency}-${isDark}-${view}`"
                        type="line"
                        height="280"
                        :options="chartOptions(currency)"
                        :series="chartSeries(currency)"
                    />

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="py-2 pr-4 font-semibold">Month</th>
                                    <th class="px-4 py-2 text-right font-semibold">Income</th>
                                    <th class="px-4 py-2 text-right font-semibold">Expenses</th>
                                    <th class="px-4 py-2 text-right font-semibold">Net</th>
                                    <th class="py-2 pl-4 text-right font-semibold">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="row in currency.months" :key="row.month" class="tabular-nums text-gray-700 dark:text-gray-200">
                                    <td class="py-2 pr-4">{{ monthLabel(row.month) }}</td>
                                    <td class="px-4 py-2 text-right text-green-600 dark:text-green-400"><Money :amount="row.income" :currency="currency.currency" /></td>
                                    <td class="px-4 py-2 text-right text-red-600 dark:text-red-400"><Money :amount="row.expense" :currency="currency.currency" /></td>
                                    <td class="px-4 py-2 text-right"><Money :amount="row.net" :currency="currency.currency" signed /></td>
                                    <td class="py-2 pl-4 text-right font-semibold" :class="row.closing < 0 ? 'text-red-600 dark:text-red-400' : ''">
                                        <Money :amount="row.closing" :currency="currency.currency" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section>
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Planned events</h3>

                <EmptyState
                    v-if="events.length === 0"
                    icon="calendar"
                    title="Plan your money before it moves"
                    description="Add the paychecks, invoices and big purchases you know about. Dates and amounts can be irregular, and you can mark them as confirmed, expected or speculative."
                >
                    <template #action>
                        <PrimaryButton type="button" @click="openCreateModal('income')">
                            <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                            Add my first planned event
                        </PrimaryButton>
                    </template>
                </EmptyState>

                <div v-else class="space-y-6">
                    <label class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400"><BulkCheckbox :bulk="bulk" page /> Select all</label>
                    <BulkDeleteBar :bulk="bulk" :url="bulkDestroy.url()" noun="planned events" note="Deleting an occurrence of a repeating event removes only that one; the rest of the series stays." />
                    <div v-for="group in groupedEvents" :key="group.month">
                        <h4 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ monthLabel(group.month) }}</h4>
                        <ul class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                            <li
                                v-for="event in group.items"
                                :key="event.id"
                                class="px-4 py-3"
                                :class="{ 'opacity-60': event.status !== 'planned' }"
                            >
                                <div class="flex flex-wrap items-center gap-3">
                                    <BulkCheckbox :bulk="bulk" :id="event.id" :label="`Select ${event.description}`" />
                                    <div class="w-14 shrink-0 text-center">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatDate(event.date) }}</p>
                                        <p v-if="isOverdue(event)" class="text-[10px] font-semibold uppercase text-red-600 dark:text-red-400">Overdue</p>
                                    </div>

                                    <CategoryIcon v-if="event.category" :category="event.category" size="h-8 w-8" />
                                    <div v-else class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-700">
                                        <DynamicIcon :name="event.type === 'income' ? 'arrow-up-circle' : 'arrow-down-circle'" class="h-4 w-4" />
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                            {{ event.description }}
                                            <span v-if="event.seriesId" class="ml-1 text-xs font-normal text-gray-400" title="Repeats">
                                                <DynamicIcon name="arrow-path" class="inline h-3 w-3" /> repeats
                                            </span>
                                        </p>
                                        <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-gray-400">
                                            <span class="rounded-full px-2 py-0.5 font-medium" :class="certaintyClass(event.certainty)">{{ event.certaintyLabel }}</span>
                                            <span v-if="event.status !== 'planned'" class="rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ event.statusLabel }}</span>
                                            <span v-if="event.account">{{ event.account.name }}</span>
                                        </p>
                                    </div>

                                    <p
                                        class="text-right text-sm font-semibold tabular-nums"
                                        :class="event.type === 'income' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                    >
                                        {{ event.type === 'income' ? '+' : '-' }}<Money :amount="event.amount" :currency="event.currency" />
                                    </p>

                                    <div v-if="event.status === 'planned'" class="flex items-center gap-1">
                                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-green-50 hover:text-green-600 dark:hover:bg-green-500/10" title="Mark as done (records the transaction)" @click="openRealize(event)">
                                            <DynamicIcon name="check-circle" class="h-4 w-4" />
                                        </button>
                                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" title="Skip" @click="skipEvent(event)">
                                            <DynamicIcon name="forward" class="h-4 w-4" />
                                        </button>
                                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" title="Edit" @click="openEditModal(event)">
                                            <DynamicIcon name="pencil" class="h-4 w-4" />
                                        </button>
                                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" title="Delete" @click="askDelete(event)">
                                            <DynamicIcon name="trash" class="h-4 w-4" />
                                        </button>
                                    </div>
                                    <div v-else-if="event.status === 'skipped'" class="flex items-center gap-1">
                                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" title="Reopen (plan it again)" @click="reopenEvent(event)">
                                            <DynamicIcon name="arrow-path" class="h-4 w-4" />
                                        </button>
                                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" title="Delete" @click="askDelete(event)">
                                            <DynamicIcon name="trash" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>

                                <div
                                    v-if="event.suggestedMatch"
                                    class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-primary-50 px-3 py-2 text-xs text-primary-800 dark:bg-primary-500/10 dark:text-primary-300"
                                >
                                    <span>
                                        Looks done: {{ event.suggestedMatch.description || 'a transaction' }}
                                        (<Money :amount="event.suggestedMatch.amount" :currency="event.currency" />, {{ formatDate(event.suggestedMatch.date) }}, {{ event.suggestedMatch.account }})
                                    </span>
                                    <button type="button" class="inline-flex items-center gap-1 font-semibold hover:underline" @click="linkMatch(event)">
                                        <DynamicIcon name="link" class="h-3.5 w-3.5" />
                                        Yes, link it
                                    </button>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
        </div>

        <ModalForm :show="showModal" :title="editingEvent ? 'Edit planned event' : 'New planned event'" @close="closeModal">
            <form id="planned-event-form" class="space-y-4" @submit.prevent="submit">
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
                    <InputLabel for="description" value="What is it?" />
                    <TextInput id="description" v-model="form.description" type="text" class="mt-1 block w-full" placeholder="Client payment, rent, new laptop..." />
                    <InputError class="mt-1" :message="form.errors.description" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="amount" value="Amount" />
                        <TextInput id="amount" v-model="form.amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="form.errors.amount" />
                    </div>
                    <div>
                        <InputLabel for="date" value="Date" />
                        <TextInput id="date" v-model="form.date" type="date" class="mt-1 block w-full" :disabled="editingEvent && editingEvent.seriesId && form.scope === 'future'" />
                        <InputError class="mt-1" :message="form.errors.date" />
                    </div>
                </div>

                <div>
                    <InputLabel for="certainty" value="How sure are you?" />
                    <SelectInput id="certainty" v-model="form.certainty">
                        <option v-for="certainty in certainties" :key="certainty.value" :value="certainty.value">{{ certainty.label }}</option>
                    </SelectInput>
                    <InputError class="mt-1" :message="form.errors.certainty" />
                </div>

                <details class="rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700">
                    <summary class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-200">More options</summary>
                    <div class="mt-3 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="account_id" value="Account (optional)" />
                                <SelectInput id="account_id" v-model="form.account_id">
                                    <option value="">Any account</option>
                                    <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                                </SelectInput>
                                <InputError class="mt-1" :message="form.errors.account_id" />
                            </div>
                            <div>
                                <InputLabel for="category_id" value="Category (optional)" />
                                <SelectInput id="category_id" v-model="form.category_id">
                                    <option value="">None yet</option>
                                    <option v-for="category in categoriesForType" :key="category.id" :value="category.id">{{ category.name }}</option>
                                </SelectInput>
                                <InputError class="mt-1" :message="form.errors.category_id" />
                            </div>
                        </div>

                        <div v-if="!editingEvent" class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="frequency" value="Repeats" />
                                <SelectInput id="frequency" v-model="form.frequency">
                                    <option value="">Does not repeat</option>
                                    <option v-for="frequency in frequencies" :key="frequency.value" :value="frequency.value">{{ frequency.label }}</option>
                                </SelectInput>
                            </div>
                            <div>
                                <InputLabel for="ends_on" value="Until (optional)" />
                                <TextInput id="ends_on" v-model="form.ends_on" type="date" class="mt-1 block w-full" :disabled="!form.frequency" />
                                <InputError class="mt-1" :message="form.errors.ends_on" />
                            </div>
                        </div>

                        <div v-if="form.type === 'income' && buckets.length && !(editingEvent && editingEvent.seriesId)">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Where should this income go? (optional)</p>
                            <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                                By default your buckets fill by their own rules. Enter amounts here to decide for this income only; the rest goes to your default bucket.
                            </p>
                            <div v-for="bucket in buckets" :key="bucket.id" class="mb-1.5 flex items-center justify-between gap-3">
                                <span class="text-sm text-gray-700 dark:text-gray-200">{{ bucket.name }} <span class="text-xs text-gray-400">{{ bucket.currency }}</span></span>
                                <TextInput v-model="overrideAmounts[bucket.id]" type="number" step="0.01" min="0" class="block w-32" placeholder="0" />
                            </div>
                            <InputError class="mt-1" :message="form.errors['allocation_overrides.0.bucket_id'] || form.errors['allocation_overrides.0.amount']" />
                        </div>

                        <div>
                            <InputLabel for="notes" value="Notes" />
                            <TextInput id="notes" v-model="form.notes" type="text" class="mt-1 block w-full" />
                        </div>
                    </div>
                </details>

                <fieldset v-if="editingEvent && editingEvent.seriesId" class="space-y-2 rounded-lg bg-gray-50 px-4 py-3 text-sm dark:bg-gray-900/40">
                    <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500">This event repeats</legend>
                    <label class="flex items-center gap-2 text-gray-700 dark:text-gray-200">
                        <input v-model="form.scope" type="radio" value="this" class="text-primary-600" /> Only this one
                    </label>
                    <label class="flex items-center gap-2 text-gray-700 dark:text-gray-200">
                        <input v-model="form.scope" type="radio" value="future" class="text-primary-600" /> This and all future ones
                    </label>
                </fieldset>
            </form>

            <template #footer>
                <SecondaryButton type="button" @click="closeModal">Cancel</SecondaryButton>
                <PrimaryButton form="planned-event-form" type="submit" :disabled="form.processing">Save</PrimaryButton>
            </template>
        </ModalForm>

        <ModalForm
            :show="realizing !== null"
            title="Mark as done"
            description="This records a real transaction. You can adjust the details first."
            @close="realizing = null"
        >
            <form id="realize-form" class="space-y-4" @submit.prevent="submitRealize">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="r_amount" value="Amount" />
                        <TextInput id="r_amount" v-model="realizeForm.amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="realizeForm.errors.amount" />
                    </div>
                    <div>
                        <InputLabel for="r_date" value="Date" />
                        <TextInput id="r_date" v-model="realizeForm.date" type="date" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="realizeForm.errors.date" />
                    </div>
                </div>
                <div>
                    <InputLabel for="r_account" value="Account" />
                    <SelectInput id="r_account" v-model="realizeForm.account_id">
                        <option value="">Select an account</option>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                    </SelectInput>
                    <InputError class="mt-1" :message="realizeForm.errors.account_id" />
                </div>
                <div>
                    <InputLabel for="r_category" value="Category" />
                    <SelectInput id="r_category" v-model="realizeForm.category_id">
                        <option value="">Select a category</option>
                        <option v-for="category in realizeCategories" :key="category.id" :value="category.id">{{ category.name }}</option>
                    </SelectInput>
                    <InputError class="mt-1" :message="realizeForm.errors.category_id" />
                </div>
                <div>
                    <InputLabel for="r_description" value="Description" />
                    <TextInput id="r_description" v-model="realizeForm.description" type="text" class="mt-1 block w-full" />
                </div>
            </form>

            <template #footer>
                <SecondaryButton type="button" @click="realizing = null">Cancel</SecondaryButton>
                <PrimaryButton form="realize-form" type="submit" :disabled="realizeForm.processing">Record it</PrimaryButton>
            </template>
        </ModalForm>

        <Modal :show="confirmingDelete !== null" @close="confirmingDelete = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delete this planned event?</h2>

                <fieldset v-if="confirmingDelete && confirmingDelete.seriesId" class="mt-3 space-y-2 text-sm">
                    <label class="flex items-center gap-2 text-gray-700 dark:text-gray-200">
                        <input v-model="deleteScope" type="radio" value="this" class="text-primary-600" /> Only this one
                    </label>
                    <label class="flex items-center gap-2 text-gray-700 dark:text-gray-200">
                        <input v-model="deleteScope" type="radio" value="future" class="text-primary-600" /> This and all future ones
                    </label>
                </fieldset>
                <p v-else class="mt-1 text-sm text-gray-600 dark:text-gray-400">This action cannot be undone.</p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="confirmingDelete = null">Cancel</SecondaryButton>
                    <DangerButton type="button" @click="deleteEvent">Delete</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
