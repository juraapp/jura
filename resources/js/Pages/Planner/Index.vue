<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Money from '@/Components/Money.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ScenarioResult from '@/Components/ScenarioResult.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SegmentedControl from '@/Components/SegmentedControl.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { withCsrfHeader } from '@/csrf';
import { index as bucketsIndex } from '@/routes/buckets';
import { index as wishlistIndex } from '@/routes/wishlist';
import { store, destroy, suggest, simulate } from '@/actions/App/Modules/Planning/Http/Controllers/PlannerController';
import { useFormat } from '@/composables/useFormat';

const format = useFormat();
const { t } = useI18n();

const props = defineProps({
    items: { type: Array, required: true },
    currencies: { type: Array, required: true },
    scenarios: { type: Array, required: true },
    views: { type: Array, required: true },
    defaultView: { type: String, default: 'expected' },
    horizons: { type: Array, required: true },
    hasFillRules: { type: Boolean, required: true },
});

const currency = ref(props.currencies[0] ?? 'COP');
const view = ref(props.defaultView);
const horizon = ref(12);

const viewHints = computed(() => ({
    conservative: t('ui.planner.hint_conservative'),
    expected: t('ui.planner.hint_expected'),
    optimistic: t('ui.planner.hint_optimistic'),
}));

const itemsInCurrency = computed(() => props.items.filter((item) => item.currency === currency.value));
const selected = ref(new Set(props.items.filter((item) => item.currency === (props.currencies[0] ?? '')).map((item) => item.id)));

/* Set by loadScenario so switching to the scenario's currency keeps the order it just loaded. */
let keepOrderOnCurrencyChange = false;

watch(currency, () => {
    selected.value = new Set(itemsInCurrency.value.map((item) => item.id));
    clearSuggestions();
    if (keepOrderOnCurrencyChange) {
        keepOrderOnCurrencyChange = false;

        return;
    }
    order.value = [];
    clearResult();
});

/* Results and suggestions were computed for the old view or horizon: rerun the builder, drop the suggestions. */
watch([view, horizon], () => {
    clearSuggestions();
    runSimulation();
});

const toggle = (id) => {
    const next = new Set(selected.value);
    next.has(id) ? next.delete(id) : next.add(id);
    selected.value = next;
};

const importance = (n) => '●'.repeat(n) + '○'.repeat(5 - n);
const formatDate = (date) => format.date(date);

/* ---- JSON helper ---- */
const pending = ref(0);
const loading = computed(() => pending.value > 0);
const error = ref('');

/**
 * Posts JSON to a planner endpoint. `isCurrent` tells whether the caller still wants this answer;
 * a stale response neither returns data nor touches the error banner.
 */
const post = async (action, body, isCurrent = () => true) => {
    pending.value++;
    error.value = '';

    try {
        const response = await fetch(action.url(), {
            method: 'POST',
            headers: withCsrfHeader({ 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }),
            body: JSON.stringify(body),
        });
        const json = await response.json();

        if (!isCurrent()) {
            return null;
        }

        if (!response.ok) {
            error.value = Object.values(json.errors ?? {}).flat()[0] ?? json.message ?? t('ui.planner.err_generic');

            return null;
        }

        return json;
    } catch (e) {
        if (isCurrent()) {
            error.value = t('ui.planner.err_server');
        }

        return null;
    } finally {
        pending.value--;
    }
};

const runParams = () => ({ currency: currency.value, view: view.value, horizon_months: horizon.value });

/* ---- suggestions ---- */
const suggestions = ref([]);
let suggestRequest = 0;

const clearSuggestions = () => {
    suggestRequest++;
    suggestions.value = [];
};

const runSuggest = async () => {
    if (selected.value.size === 0) {
        error.value = t('ui.planner.err_pick');

        return;
    }

    const request = ++suggestRequest;
    const json = await post(suggest, { ...runParams(), item_ids: [...selected.value] }, () => request === suggestRequest);
    if (json) suggestions.value = json.suggestions;
};

/* ---- builder ---- */
const order = ref([]); // [{ itemId, notBefore }]
const result = ref(null);
const itemById = (id) => props.items.find((item) => item.id === id);

/*
 * Only wanted items in the planner's currency can be simulated or saved; others (bought, dropped) stay visible but left out.
 * A saved scenario's line carries the server's `wanted` flag; rows built from the wish list have none and are wanted.
 */
const isPlannable = (row, inCurrency = currency.value) => row.wanted !== false && itemById(row.itemId)?.currency === inCurrency;
const scenarioHasPlannableLines = (scenario) => scenario.lines.some((line) => isPlannable(line, scenario.currency));
const lineName = (row) => itemById(row.itemId)?.name ?? row.name ?? t('ui.planner.item_n', { id: row.itemId });
const plannableRows = computed(() => order.value.filter((row) => isPlannable(row)));
/* The incomes the last run counted (the same for every suggestion and the builder of this currency, view and horizon). */
const coverage = computed(() => (result.value ?? suggestions.value[0])?.incomes ?? null);

const viewLabel = (value) => props.views.find((candidate) => candidate.value === value)?.label ?? value;

const useOrder = (ids) => {
    order.value = ids.map((id) => ({ itemId: id, notBefore: '' }));
    runSimulation();
    document.getElementById('builder')?.scrollIntoView({ behavior: 'smooth' });
};

const startFromSelection = () => useOrder([...selected.value]);

const move = (index, delta) => {
    const target = index + delta;
    if (target < 0 || target >= order.value.length) return;
    const next = [...order.value];
    [next[index], next[target]] = [next[target], next[index]];
    order.value = next;
    runSimulation();
};

const removeLine = (index) => {
    order.value = order.value.filter((_, i) => i !== index);
    runSimulation();
};

const linesPayload = (rows) => rows.map((row) => ({ item_id: row.itemId, not_before: row.notBefore || null }));

/* Each run gets a number; only the latest one may set the result, so a slow older response cannot overwrite a newer one. */
let simulationRequest = 0;

const clearResult = () => {
    simulationRequest++;
    result.value = null;
};

const runSimulation = async () => {
    if (plannableRows.value.length === 0) {
        clearResult();

        return;
    }

    const request = ++simulationRequest;
    const json = await post(simulate, { ...runParams(), lines: linesPayload(plannableRows.value) }, () => request === simulationRequest);
    if (json) result.value = json;
};

/* ---- save ---- */
const saveForm = useForm({ name: '', currency: '', view: '', horizon_months: 12, lines: [] });
const showSave = ref(false);

const saveOrder = (ids) => {
    order.value = ids.map((id) => ({ itemId: id, notBefore: '' }));
    runSimulation();
    showSave.value = true;
};

const submitSave = () => {
    saveForm.transform((data) => ({ ...data, ...runParams(), lines: linesPayload(plannableRows.value) })).post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            showSave.value = false;
            saveForm.reset();
        },
    });
};

/* ---- scenarios & comparison ---- */
const compareIds = ref(new Set());
const compared = ref([]);

const toggleCompare = async (scenario) => {
    const next = new Set(compareIds.value);
    next.has(scenario.id) ? next.delete(scenario.id) : next.add(scenario.id);
    compareIds.value = next;
    await refreshComparison();
};

let comparisonRequest = 0;

const refreshComparison = async () => {
    const request = ++comparisonRequest;
    const chosen = props.scenarios.filter((scenario) => compareIds.value.has(scenario.id));
    const runs = [];

    for (const scenario of chosen) {
        /* A scenario with nothing left to plan shows the nothing_plannable hint in the saved-plans list instead. */
        const lines = scenario.lines.filter((line) => isPlannable(line, scenario.currency));
        if (lines.length === 0) {
            continue;
        }

        const json = await post(simulate, {
            currency: scenario.currency,
            view: scenario.view,
            horizon_months: scenario.horizonMonths,
            lines: linesPayload(lines.map((line) => ({ itemId: line.itemId, notBefore: line.notBefore }))),
        }, () => request === comparisonRequest);
        if (request !== comparisonRequest) {
            return;
        }
        if (json) runs.push({ scenario, result: json });
    }

    compared.value = runs;
};

const loadScenario = (scenario) => {
    if (scenario.currency !== currency.value) {
        keepOrderOnCurrencyChange = true;
    }
    /* A new view or horizon already reruns the simulation through its watcher; only run it here when neither changes. */
    const settingsChange = view.value !== scenario.view || horizon.value !== scenario.horizonMonths;
    currency.value = scenario.currency;
    view.value = scenario.view;
    horizon.value = scenario.horizonMonths;
    order.value = scenario.lines.map((line) => ({ itemId: line.itemId, name: line.name, wanted: line.wanted, notBefore: line.notBefore ?? '' }));
    if (!settingsChange) {
        runSimulation();
    }
    document.getElementById('builder')?.scrollIntoView({ behavior: 'smooth' });
};

const deleteScenario = (scenario) => {
    compareIds.value.delete(scenario.id);
    router.delete(destroy.url(scenario.id), { preserveScroll: true, onSuccess: () => refreshComparison() });
};

const comparisonRows = computed(() => {
    const ids = [];
    compared.value.forEach(({ result: run }) => run.lines.forEach((line) => ids.includes(line.itemId) || ids.push(line.itemId)));

    return ids.map((id) => ({
        id,
        name: itemById(id)?.name
            ?? compared.value.flatMap(({ scenario }) => scenario.lines).find((line) => line.itemId === id)?.name
            ?? t('ui.planner.item_n', { id }),
        cells: compared.value.map(({ result: run }) => run.lines.find((line) => line.itemId === id) ?? null),
    }));
});
</script>

<template>
    <Head :title="$t('ui.planner.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">{{ $t('ui.planner.title') }}</h2>
        </template>

        <div class="mx-auto max-w-6xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
            <EmptyState
                v-if="items.length === 0"
                icon="scale"
                :title="$t('ui.planner.empty_title')"
                :description="$t('ui.planner.empty_hint')"
            >
                <template #action>
                    <PrimaryButton :as="Link" :href="wishlistIndex.url()">{{ $t('ui.planner.go_wishlist') }}</PrimaryButton>
                </template>
            </EmptyState>

            <template v-else>
                <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    {{ $t('ui.planner.intro') }}
                </p>

                <div v-if="!hasFillRules" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-300">
                    {{ $t('ui.planner.no_rules_info') }}
                    <Link :href="bucketsIndex.url()" class="font-semibold underline">{{ $t('ui.planner.add_rule') }}</Link>
                    {{ $t('ui.planner.add_rule_tail') }}
                </div>

                <div class="flex flex-wrap items-end gap-4">
                    <div v-if="currencies.length > 1">
                        <InputLabel for="currency" :value="$t('ui.planner.currency')" />
                        <SelectInput id="currency" v-model="currency" class="!mt-1 w-28">
                            <option v-for="code in currencies" :key="code" :value="code">{{ code }}</option>
                        </SelectInput>
                    </div>
                    <div>
                        <p class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('ui.planner.how_sure') }}</p>
                        <SegmentedControl v-model="view" :options="views" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ viewHints[view] }}</p>
                    </div>
                    <div>
                        <InputLabel for="horizon" :value="$t('ui.planner.look_ahead')" />
                        <SelectInput id="horizon" v-model="horizon" class="!mt-1 w-32">
                            <option v-for="months in horizons" :key="months" :value="months">{{ $t('ui.planner.months', { count: months }) }}</option>
                        </SelectInput>
                    </div>
                </div>

                <div v-if="coverage" class="space-y-0.5 text-xs text-gray-500 dark:text-gray-400" data-test="income-coverage">
                    <p>{{ $t('ui.planner.counting_incomes', { counted: coverage.counted, total: coverage.total, amount: format.money(coverage.countedAmount, currency) }) }}</p>
                    <p v-if="coverage.overdue > 0">{{ $t('ui.planner.overdue_counted', { count: coverage.overdue }, coverage.overdue) }}</p>
                    <p v-if="coverage.offBudget > 0">{{ $t('ui.planner.off_budget_incomes', { count: coverage.offBudget, amount: format.money(coverage.offBudgetAmount, currency) }, coverage.offBudget) }}</p>
                </div>

                <p v-if="error" class="rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-400">{{ error }}</p>

                <section class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('ui.planner.step1') }}</h3>
                    <p v-if="itemsInCurrency.length === 0" class="text-sm text-gray-400">{{ $t('ui.planner.no_items', { currency }) }}</p>
                    <ul v-else class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                        <li v-for="item in itemsInCurrency" :key="item.id">
                            <label class="flex cursor-pointer items-center gap-3 px-4 py-2.5">
                                <input type="checkbox" class="rounded border-gray-300 text-primary-600" :checked="selected.has(item.id)" @change="toggle(item.id)" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-gray-900 dark:text-white">{{ item.name }}</span>
                                    <span class="text-xs text-gray-400">
                                        <span class="tracking-widest text-primary-500" :title="$t('ui.planner.importance', { value: item.importance })">{{ importance(item.importance) }}</span>
                                        <template v-if="item.bucket"> &middot; {{ item.bucket.name }}</template>
                                        <template v-else> &middot; {{ $t('ui.planner.from_ready_only') }}</template>
                                        <template v-if="item.desiredBy"> &middot; {{ $t('ui.planner.by', { date: formatDate(item.desiredBy) }) }}</template>
                                    </span>
                                </span>
                                <span class="text-sm font-semibold tabular-nums text-gray-900 dark:text-white"><Money :amount="item.price" :currency="item.currency" /></span>
                            </label>
                        </li>
                    </ul>
                    <div class="flex flex-wrap gap-2">
                        <PrimaryButton type="button" :disabled="loading || selected.size === 0" @click="runSuggest">
                            <DynamicIcon name="sparkles" class="h-4 w-4" />
                            {{ $t('ui.planner.suggest') }}
                        </PrimaryButton>
                        <SecondaryButton type="button" :disabled="selected.size === 0" @click="startFromSelection">{{ $t('ui.planner.build') }}</SecondaryButton>
                    </div>
                </section>

                <section v-if="suggestions.length" class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('ui.planner.step2') }}</h3>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div
                            v-for="(suggestion, index) in suggestions"
                            :key="suggestion.strategy"
                            class="rounded-xl border bg-white p-4 shadow-sm dark:bg-gray-800"
                            :class="index === 0 ? 'border-primary-300 ring-1 ring-primary-200 dark:border-primary-500/50 dark:ring-primary-500/20' : 'border-gray-200 dark:border-gray-700'"
                        >
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ suggestion.label }}
                                    <span v-if="index === 0" class="ml-1 rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-400">{{ $t('ui.planner.recommended') }}</span>
                                </h4>
                            </div>
                            <ScenarioResult :result="suggestion" :currency="currency" :horizon-months="horizon" />
                            <div class="mt-3 flex gap-2">
                                <SecondaryButton type="button" @click="useOrder(suggestion.order)">{{ $t('ui.planner.adjust') }}</SecondaryButton>
                                <SecondaryButton type="button" @click="saveOrder(suggestion.order)">{{ $t('ui.planner.save_compare') }}</SecondaryButton>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="builder" class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ suggestions.length ? '3.' : '2.' }} {{ $t('ui.planner.your_order') }}</h3>
                    <p v-if="order.length === 0" class="text-sm text-gray-400">
                        {{ $t('ui.planner.order_hint') }}
                    </p>

                    <div v-else class="grid gap-4 lg:grid-cols-2">
                        <ol class="space-y-2">
                            <li v-for="(row, index) in order" :key="row.itemId" class="rounded-xl border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <div class="flex items-center gap-2">
                                    <span class="w-5 text-xs font-semibold text-gray-400">{{ index + 1 }}.</span>
                                    <span class="min-w-0 flex-1 truncate text-sm font-medium" :class="isPlannable(row) ? 'text-gray-900 dark:text-white' : 'text-gray-400 line-through dark:text-gray-500'">{{ lineName(row) }}</span>
                                    <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 disabled:opacity-30 dark:hover:bg-gray-700" :disabled="index === 0" :title="$t('ui.planner.buy_earlier')" :aria-label="$t('ui.planner.buy_earlier')" @click="move(index, -1)">
                                        <DynamicIcon name="chevron-down" class="h-4 w-4 rotate-180" />
                                    </button>
                                    <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 disabled:opacity-30 dark:hover:bg-gray-700" :disabled="index === order.length - 1" :title="$t('ui.planner.buy_later')" :aria-label="$t('ui.planner.buy_later')" @click="move(index, 1)">
                                        <DynamicIcon name="chevron-down" class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" :title="$t('ui.planner.remove')" :aria-label="$t('ui.planner.remove')" @click="removeLine(index)">
                                        <DynamicIcon name="x-mark" class="h-4 w-4" />
                                    </button>
                                </div>
                                <p v-if="!isPlannable(row)" class="mt-1 pl-7 text-xs text-amber-600 dark:text-amber-400">{{ $t('ui.planner.left_out') }}</p>
                                <div v-else class="mt-1.5 flex items-center gap-2 pl-7 text-xs text-gray-500 dark:text-gray-400">
                                    <label :for="`nb-${row.itemId}`">{{ $t('ui.planner.not_before') }}</label>
                                    <input :id="`nb-${row.itemId}`" v-model="row.notBefore" type="date" class="rounded-md border-gray-300 py-0.5 text-xs dark:border-gray-600 dark:bg-gray-700" @change="runSimulation" />
                                </div>
                            </li>
                        </ol>

                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <p v-if="plannableRows.length === 0" class="text-sm text-gray-400">{{ $t('ui.planner.nothing_plannable') }}</p>
                            <p v-else-if="!result" class="text-sm text-gray-400">{{ $t('ui.planner.working') }}</p>
                            <ScenarioResult v-else :result="result" :currency="currency" :horizon-months="horizon" />
                            <div class="mt-3">
                                <SecondaryButton v-if="!showSave" type="button" @click="showSave = true">{{ $t('ui.planner.save_compare') }}</SecondaryButton>
                                <form v-else class="flex flex-wrap items-end gap-2" @submit.prevent="submitSave">
                                    <div class="flex-1">
                                        <InputLabel for="scenario_name" :value="$t('ui.planner.name_plan')" />
                                        <TextInput id="scenario_name" v-model="saveForm.name" type="text" class="mt-1 block w-full" :placeholder="$t('ui.planner.name_placeholder')" />
                                        <InputError class="mt-1" :message="saveForm.errors.name || saveForm.errors.lines" />
                                    </div>
                                    <PrimaryButton type="submit" :disabled="saveForm.processing">{{ $t('ui.planner.save') }}</PrimaryButton>
                                </form>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="scenarios.length" class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('ui.planner.saved_plans') }}</h3>
                    <ul class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                        <li v-for="scenario in scenarios" :key="scenario.id" class="flex flex-wrap items-center gap-3 px-4 py-2.5">
                            <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-3">
                                <input type="checkbox" class="rounded border-gray-300 text-primary-600" :checked="compareIds.has(scenario.id)" @change="toggleCompare(scenario)" />
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-gray-900 dark:text-white">{{ scenario.name }}</span>
                                    <span class="text-xs text-gray-400">{{ $t('ui.planner.plan_meta', { count: scenario.lines.length }) }} &middot; {{ scenario.currency }} &middot; {{ viewLabel(scenario.view) }} &middot; {{ $t('ui.planner.months', { count: scenario.horizonMonths }) }}</span>
                                </span>
                            </label>
                            <p v-if="compareIds.has(scenario.id) && !scenarioHasPlannableLines(scenario)" class="order-last w-full pl-7 text-xs text-amber-600 dark:text-amber-400">{{ $t('ui.planner.nothing_plannable') }}</p>
                            <button type="button" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400" @click="loadScenario(scenario)">{{ $t('ui.planner.open') }}</button>
                            <button type="button" class="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" :title="$t('ui.common.delete')" :aria-label="$t('ui.common.delete')" @click="deleteScenario(scenario)">
                                <DynamicIcon name="trash" class="h-4 w-4" />
                            </button>
                        </li>
                    </ul>
                    <p class="text-xs text-gray-400">{{ $t('ui.planner.compare_hint') }}</p>
                </section>

                <section v-if="compared.length > 1" class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('ui.planner.comparison') }}</h3>
                    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-3 font-semibold">{{ $t('ui.planner.item') }}</th>
                                    <th v-for="run in compared" :key="run.scenario.id" class="px-4 py-3 font-semibold">{{ run.scenario.name }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="row in comparisonRows" :key="row.id">
                                    <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ row.name }}</td>
                                    <td v-for="(cell, index) in row.cells" :key="index" class="px-4 py-2 tabular-nums">
                                        <span v-if="!cell" class="text-gray-300">{{ $t('ui.planner.not_in_plan') }}</span>
                                        <span v-else-if="cell.purchaseDate" class="text-green-600 dark:text-green-400">{{ formatDate(cell.purchaseDate) }}</span>
                                        <span v-else class="text-amber-600 dark:text-amber-400">{{ $t('ui.planner.not_affordable') }}</span>
                                    </td>
                                </tr>
                                <tr class="bg-gray-50 dark:bg-gray-900/40">
                                    <td class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $t('ui.planner.score') }}</td>
                                    <td v-for="run in compared" :key="run.scenario.id" class="px-4 py-2 tabular-nums text-gray-700 dark:text-gray-200">{{ run.result.score }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
