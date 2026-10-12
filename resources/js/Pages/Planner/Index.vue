<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
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

const props = defineProps({
    items: { type: Array, required: true },
    currencies: { type: Array, required: true },
    scenarios: { type: Array, required: true },
    views: { type: Array, required: true },
    horizons: { type: Array, required: true },
    hasFillRules: { type: Boolean, required: true },
});

const currency = ref(props.currencies[0] ?? 'COP');
const view = ref('conservative');
const horizon = ref(12);

const viewHints = {
    conservative: 'Only confirmed income is counted.',
    expected: 'Confirmed and expected income are counted.',
    optimistic: 'All planned income is counted, even speculative.',
};

const itemsInCurrency = computed(() => props.items.filter((item) => item.currency === currency.value));
const selected = ref(new Set(props.items.filter((item) => item.currency === (props.currencies[0] ?? '')).map((item) => item.id)));

watch(currency, () => {
    selected.value = new Set(itemsInCurrency.value.map((item) => item.id));
    suggestions.value = [];
    order.value = [];
    result.value = null;
});

const toggle = (id) => {
    const next = new Set(selected.value);
    next.has(id) ? next.delete(id) : next.add(id);
    selected.value = next;
};

const importance = (n) => '●'.repeat(n) + '○'.repeat(5 - n);
const formatDate = (date) => new Date(`${date}T00:00:00`).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });

/* ---- JSON helper ---- */
const loading = ref(false);
const error = ref('');

const post = async (action, body) => {
    loading.value = true;
    error.value = '';

    try {
        const response = await fetch(action.url(), {
            method: 'POST',
            headers: withCsrfHeader({ 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }),
            body: JSON.stringify(body),
        });
        const json = await response.json();

        if (!response.ok) {
            error.value = Object.values(json.errors ?? {}).flat()[0] ?? json.message ?? 'Something went wrong.';

            return null;
        }

        return json;
    } catch (e) {
        error.value = 'Could not reach the server.';

        return null;
    } finally {
        loading.value = false;
    }
};

const runParams = () => ({ currency: currency.value, view: view.value, horizon_months: horizon.value });

/* ---- suggestions ---- */
const suggestions = ref([]);

const runSuggest = async () => {
    if (selected.value.size === 0) {
        error.value = 'Pick at least one item first.';

        return;
    }

    const json = await post(suggest, { ...runParams(), item_ids: [...selected.value] });
    if (json) suggestions.value = json.suggestions;
};

/* ---- builder ---- */
const order = ref([]); // [{ itemId, notBefore }]
const result = ref(null);
const itemById = (id) => props.items.find((item) => item.id === id);

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
    order.value.length ? runSimulation() : (result.value = null);
};

const linesPayload = (rows) => rows.map((row) => ({ item_id: row.itemId, not_before: row.notBefore || null }));

const runSimulation = async () => {
    if (order.value.length === 0) return;
    const json = await post(simulate, { ...runParams(), lines: linesPayload(order.value) });
    if (json) result.value = json;
};

/* ---- save ---- */
const saveForm = useForm({ name: '', currency: '', view: '', horizon_months: 12, lines: [] });
const showSave = ref(false);

const saveOrder = (ids) => {
    order.value = ids.map((id) => ({ itemId: id, notBefore: '' }));
    showSave.value = true;
};

const submitSave = () => {
    saveForm.transform((data) => ({ ...data, ...runParams(), lines: linesPayload(order.value) })).post(store.url(), {
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

const refreshComparison = async () => {
    const chosen = props.scenarios.filter((scenario) => compareIds.value.has(scenario.id));
    const runs = [];

    for (const scenario of chosen) {
        const json = await post(simulate, {
            currency: scenario.currency,
            view: scenario.view,
            horizon_months: scenario.horizonMonths,
            lines: linesPayload(scenario.lines.map((line) => ({ itemId: line.itemId, notBefore: line.notBefore }))),
        });
        if (json) runs.push({ scenario, result: json });
    }

    compared.value = runs;
};

const loadScenario = (scenario) => {
    currency.value = scenario.currency;
    view.value = scenario.view;
    horizon.value = scenario.horizonMonths;
    order.value = scenario.lines.map((line) => ({ itemId: line.itemId, notBefore: line.notBefore ?? '' }));
    runSimulation();
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
        name: itemById(id)?.name ?? `Item ${id}`,
        cells: compared.value.map(({ result: run }) => run.lines.find((line) => line.itemId === id) ?? null),
    }));
});
</script>

<template>
    <Head title="Planner" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Planner</h2>
        </template>

        <div class="mx-auto max-w-6xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
            <EmptyState
                v-if="items.length === 0"
                icon="scale"
                title="Add something you want first"
                description="The planner compares ways of buying the things on your wish list, using the money in your buckets and the income you expect."
            >
                <template #action>
                    <Link :href="wishlistIndex.url()">
                        <PrimaryButton type="button">Go to the wish list</PrimaryButton>
                    </Link>
                </template>
            </EmptyState>

            <template v-else>
                <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    Pick what you want, and the planner suggests the order that gets you the things you want most, soonest. It replays your planned income
                    through your buckets' filling rules. Nothing here changes your real money.
                </p>

                <div v-if="!hasFillRules" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                    None of your buckets fills automatically, so the planner only counts the money already in them.
                    <Link :href="bucketsIndex.url()" class="font-semibold underline">Add a fill rule in Buckets</Link>
                    to let your income reach them.
                </div>

                <div class="flex flex-wrap items-end gap-4">
                    <div v-if="currencies.length > 1">
                        <InputLabel for="currency" value="Currency" />
                        <SelectInput id="currency" v-model="currency" class="!mt-1 w-28">
                            <option v-for="code in currencies" :key="code" :value="code">{{ code }}</option>
                        </SelectInput>
                    </div>
                    <div>
                        <p class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">How sure should the income be?</p>
                        <SegmentedControl v-model="view" :options="views" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ viewHints[view] }}</p>
                    </div>
                    <div>
                        <InputLabel for="horizon" value="Look ahead" />
                        <SelectInput id="horizon" v-model="horizon" class="!mt-1 w-32">
                            <option v-for="months in horizons" :key="months" :value="months">{{ months }} months</option>
                        </SelectInput>
                    </div>
                </div>

                <p v-if="error" class="rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-400">{{ error }}</p>

                <section class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">1. What do you want to buy?</h3>
                    <p v-if="itemsInCurrency.length === 0" class="text-sm text-gray-400">No wanted items in {{ currency }}.</p>
                    <ul v-else class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                        <li v-for="item in itemsInCurrency" :key="item.id">
                            <label class="flex cursor-pointer items-center gap-3 px-4 py-2.5">
                                <input type="checkbox" class="rounded border-gray-300 text-primary-600" :checked="selected.has(item.id)" @change="toggle(item.id)" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-gray-900 dark:text-white">{{ item.name }}</span>
                                    <span class="text-xs text-gray-400">
                                        <span class="tracking-widest text-primary-500" :title="`Importance ${item.importance} of 5`">{{ importance(item.importance) }}</span>
                                        <template v-if="item.bucket"> &middot; {{ item.bucket.name }}</template>
                                        <template v-else> &middot; default bucket</template>
                                        <template v-if="item.desiredBy"> &middot; by {{ formatDate(item.desiredBy) }}</template>
                                    </span>
                                </span>
                                <span class="text-sm font-semibold tabular-nums text-gray-900 dark:text-white"><Money :amount="item.price" :currency="item.currency" /></span>
                            </label>
                        </li>
                    </ul>
                    <div class="flex flex-wrap gap-2">
                        <PrimaryButton type="button" :disabled="loading || selected.size === 0" @click="runSuggest">
                            <DynamicIcon name="sparkles" class="mr-1.5 h-4 w-4" />
                            Suggest the best order
                        </PrimaryButton>
                        <SecondaryButton type="button" :disabled="selected.size === 0" @click="startFromSelection">Build my own order</SecondaryButton>
                    </div>
                </section>

                <section v-if="suggestions.length" class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">2. Suggested orders</h3>
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
                                    <span v-if="index === 0" class="ml-1 rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-400">Recommended</span>
                                </h4>
                            </div>
                            <ScenarioResult :result="suggestion" :currency="currency" :horizon-months="horizon" />
                            <div class="mt-3 flex gap-2">
                                <SecondaryButton type="button" @click="useOrder(suggestion.order)">Adjust this order</SecondaryButton>
                                <SecondaryButton type="button" @click="saveOrder(suggestion.order)">Save for comparing</SecondaryButton>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="builder" class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ suggestions.length ? '3.' : '2.' }} Your own order</h3>
                    <p v-if="order.length === 0" class="text-sm text-gray-400">
                        Use "Build my own order" or "Adjust this order" above. Items are bought strictly in the order shown, so moving one earlier makes the others wait.
                    </p>

                    <div v-else class="grid gap-4 lg:grid-cols-2">
                        <ol class="space-y-2">
                            <li v-for="(row, index) in order" :key="row.itemId" class="rounded-xl border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <div class="flex items-center gap-2">
                                    <span class="w-5 text-xs font-semibold text-gray-400">{{ index + 1 }}.</span>
                                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900 dark:text-white">{{ itemById(row.itemId)?.name }}</span>
                                    <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 disabled:opacity-30 dark:hover:bg-gray-700" :disabled="index === 0" title="Buy earlier" @click="move(index, -1)">
                                        <DynamicIcon name="chevron-down" class="h-4 w-4 rotate-180" />
                                    </button>
                                    <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 disabled:opacity-30 dark:hover:bg-gray-700" :disabled="index === order.length - 1" title="Buy later" @click="move(index, 1)">
                                        <DynamicIcon name="chevron-down" class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" title="Remove" @click="removeLine(index)">
                                        <DynamicIcon name="x-mark" class="h-4 w-4" />
                                    </button>
                                </div>
                                <div class="mt-1.5 flex items-center gap-2 pl-7 text-xs text-gray-500 dark:text-gray-400">
                                    <label :for="`nb-${row.itemId}`">Not before</label>
                                    <input :id="`nb-${row.itemId}`" v-model="row.notBefore" type="date" class="rounded-md border-gray-300 py-0.5 text-xs dark:border-gray-600 dark:bg-gray-700" @change="runSimulation" />
                                </div>
                            </li>
                        </ol>

                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <p v-if="!result" class="text-sm text-gray-400">Working it out...</p>
                            <ScenarioResult v-else :result="result" :currency="currency" :horizon-months="horizon" />
                            <div class="mt-3">
                                <SecondaryButton v-if="!showSave" type="button" @click="showSave = true">Save for comparing</SecondaryButton>
                                <form v-else class="flex flex-wrap items-end gap-2" @submit.prevent="submitSave">
                                    <div class="flex-1">
                                        <InputLabel for="scenario_name" value="Name this plan" />
                                        <TextInput id="scenario_name" v-model="saveForm.name" type="text" class="mt-1 block w-full" placeholder="Bike first, then the monitors" />
                                        <InputError class="mt-1" :message="saveForm.errors.name || saveForm.errors.lines" />
                                    </div>
                                    <PrimaryButton type="submit" :disabled="saveForm.processing">Save</PrimaryButton>
                                </form>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="scenarios.length" class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Saved plans</h3>
                    <ul class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                        <li v-for="scenario in scenarios" :key="scenario.id" class="flex flex-wrap items-center gap-3 px-4 py-2.5">
                            <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-3">
                                <input type="checkbox" class="rounded border-gray-300 text-primary-600" :checked="compareIds.has(scenario.id)" @change="toggleCompare(scenario)" />
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-gray-900 dark:text-white">{{ scenario.name }}</span>
                                    <span class="text-xs text-gray-400">{{ scenario.lines.length }} items &middot; {{ scenario.currency }} &middot; {{ scenario.view }} &middot; {{ scenario.horizonMonths }} months</span>
                                </span>
                            </label>
                            <button type="button" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400" @click="loadScenario(scenario)">Open</button>
                            <button type="button" class="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" title="Delete" @click="deleteScenario(scenario)">
                                <DynamicIcon name="trash" class="h-4 w-4" />
                            </button>
                        </li>
                    </ul>
                    <p class="text-xs text-gray-400">Tick two or more plans to compare them side by side.</p>
                </section>

                <section v-if="compared.length > 1" class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Comparison</h3>
                    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-3 font-semibold">Item</th>
                                    <th v-for="run in compared" :key="run.scenario.id" class="px-4 py-3 font-semibold">{{ run.scenario.name }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="row in comparisonRows" :key="row.id">
                                    <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ row.name }}</td>
                                    <td v-for="(cell, index) in row.cells" :key="index" class="px-4 py-2 tabular-nums">
                                        <span v-if="!cell" class="text-gray-300">not in plan</span>
                                        <span v-else-if="cell.purchaseDate" class="text-green-600 dark:text-green-400">{{ formatDate(cell.purchaseDate) }}</span>
                                        <span v-else class="text-amber-600 dark:text-amber-400">not affordable</span>
                                    </td>
                                </tr>
                                <tr class="bg-gray-50 dark:bg-gray-900/40">
                                    <td class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Overall score (lower is better)</td>
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
