<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SelectInput from '@/Components/SelectInput.vue';
import { useToday } from '@/composables/useToday';
import { useFormat } from '@/composables/useFormat';
import {
    canChooseWhen,
    confirmDate,
    futurePrompt,
    initialWhen,
    keepBucket,
    payload,
    spendableBuckets,
    submitTarget,
} from '@/moneyMovement';
import { store as storeTransaction, update as updateTransaction, editData } from '@/actions/App/Modules/Ledger/Http/Controllers/TransactionController';
import { store as storePlanned, update as updatePlanned, realize } from '@/actions/App/Modules/Planning/Http/Controllers/PlannedEventController';
import { update as updateSeries, editData as seriesEditData } from '@/actions/App/Modules/Planning/Http/Controllers/PlannedSeriesController';
import { spendOptions } from '@/actions/App/Modules/Planning/Http/Controllers/BucketController';

/**
 * The body of the one money-movement form: a real transaction, a plan, a repeating plan, or the confirm of
 * a plan. The modal shell (GlobalTransactionModal) owns the title, the buttons and the shared options.
 */
const props = defineProps({
    intent: { type: String, default: 'create' },
    transactionId: { type: Number, default: null },
    event: { type: Object, default: null },
    seriesId: { type: Number, default: null },
    defaultType: { type: String, default: 'expense' },
    defaultWhen: { type: String, default: 'happened' },
    /** { accounts, expenseCategories, incomeCategories, planned: { frequencies, certainties, buckets } | null } */
    options: { type: Object, required: true },
    loadPlannedOptions: { type: Function, required: true },
});

const emit = defineEmits(['saved', 'busy', 'failed', 'edit-series', 'title']);

const { t } = useI18n();
const todayDate = useToday();
const format = useFormat();
const today = todayDate();

const when = ref(initialWhen(props.intent, props.defaultWhen));
const type = ref(props.event?.type ?? props.defaultType);
const loading = ref(true);
/** Accounts of the record being edited, so an archived one still shows its name. */
const editAccounts = ref([]);
/** Buckets an expense can be paid from (spend-options), fetched on every open since their funds change. */
const buckets = ref([]);
const overrides = reactive({});
const series = ref(null);

const form = useForm({
    account_id: '',
    category_id: '',
    from_account_id: '',
    to_account_id: '',
    amount: '',
    date: today,
    time: '',
    description: '',
    notes: '',
    payment_method: '',
    bucket_id: '',
    certainty: 'expected',
    frequency: '',
    ends_on: '',
    next_date: today,
    auto_post: false,
    scope: 'this',
    confirm_future: false,
});

watch(() => form.processing || loading.value, (busy) => emit('busy', busy), { immediate: true });

const fetchJson = async (url) => {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
};

const isCreate = computed(() => props.intent === 'create');
const planned = computed(() => when.value === 'planned');
const isTransfer = computed(() => type.value === 'transfer');
const plannedOptions = computed(() => props.options.planned ?? { frequencies: [], certainties: [], buckets: [] });

const tabs = computed(() => [
    { value: 'expense', label: t('ui.transaction_modal.expense') },
    { value: 'income', label: t('ui.transaction_modal.income') },
    { value: 'transfer', label: t('ui.transaction_modal.transfer'), disabled: planned.value },
]);

const accounts = computed(() => [
    ...props.options.accounts,
    ...editAccounts.value.filter((account) => !props.options.accounts.some((active) => active.id === account.id)),
]);
const categories = computed(() => (type.value === 'income' ? props.options.incomeCategories : props.options.expenseCategories));
const selectedAccount = computed(() => accounts.value.find((account) => account.id === Number(form.account_id)) ?? null);

const bucketChoices = computed(() => (planned.value ? [] : spendableBuckets(buckets.value, { type: type.value, account: selectedAccount.value })));
/** A funded spending bucket that already counts the chosen category. */
const trackingBucket = computed(() => {
    if (planned.value || type.value !== 'expense' || !form.category_id || !selectedAccount.value) {
        return null;
    }

    return buckets.value.find((bucket) => bucket.funded && bucket.categoryId === Number(form.category_id) && bucket.currency === selectedAccount.value.currency) ?? null;
});

/** Buckets an income plan can split into: the account's currency, or every currency while no account is chosen. */
const splitBuckets = computed(() => plannedOptions.value.buckets.filter((bucket) => !selectedAccount.value || bucket.currency === selectedAccount.value.currency));

const repeats = computed(() => Boolean(form.frequency) || props.intent === 'editSeries');
const prompt = computed(() => futurePrompt(when.value, form.date, today, { intent: props.intent, repeats: repeats.value }));
/** A future date the server refused on an edit (it asks before recording a future transaction as real). */
const futureEdit = computed(() => props.intent === 'editTransaction' && form.date > today && Boolean(form.errors.date));

const title = computed(() => {
    switch (props.intent) {
        case 'editTransaction':
            return t('ui.transaction_modal.edit');
        case 'editPlanned':
            return t('ui.transaction_modal.edit_planned');
        case 'editSeries':
            return t('ui.transaction_modal.edit_series');
        case 'confirmPlanned':
            return t('ui.transaction_modal.confirm_title', { description: props.event?.description ?? '' });
        default:
            return planned.value ? t('ui.transaction_modal.new_planned') : t('ui.transaction_modal.new');
    }
});
watch(title, (value) => emit('title', value), { immediate: true });

const fillPlan = (source) => {
    type.value = source.type;
    form.description = source.description ?? '';
    form.amount = String(source.amount);
    form.certainty = source.certainty;
    form.account_id = source.account?.id ?? '';
    form.category_id = source.category?.id ?? '';
    form.notes = source.notes ?? '';
    form.auto_post = Boolean(source.autoPost);
    (source.allocationOverrides ?? []).forEach((row) => {
        overrides[row.bucket_id] = String(row.amount);
    });
    if (source.account) {
        editAccounts.value = [{ ...source.account, currency: source.currency }];
    }
};

const load = async () => {
    form.time = props.intent === 'create' ? new Date().toTimeString().slice(0, 5) : '';

    if (props.intent === 'editTransaction') {
        const data = await fetchJson(editData.url(props.transactionId));
        type.value = data.type;
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
    } else if (props.intent === 'editPlanned') {
        fillPlan(props.event);
        form.date = props.event.date;
    } else if (props.intent === 'editSeries') {
        series.value = await fetchJson(seriesEditData.url(props.seriesId));
        fillPlan(series.value);
        form.frequency = series.value.frequency;
        form.next_date = series.value.nextDate ?? today;
        form.ends_on = series.value.endsOn ?? '';
    } else if (props.intent === 'confirmPlanned') {
        fillPlan(props.event);
        form.date = confirmDate(props.event.date, today);
    }

    if (planned.value) {
        await props.loadPlannedOptions();
    }

    /* The saved bucket is fetched along with the others even if it was completed since, so the link is kept. */
    const include = form.bucket_id ? { query: { include: form.bucket_id } } : undefined;
    buckets.value = (await fetchJson(spendOptions.url(include))).buckets;
};

onMounted(async () => {
    try {
        await load();
    } catch {
        emit('failed');
    } finally {
        loading.value = false;
    }
});

const chooseWhen = async (value) => {
    when.value = value;
    if (value === 'planned') {
        if (type.value === 'transfer') {
            type.value = 'expense';
        }
        try {
            await props.loadPlannedOptions();
        } catch {
            emit('failed');
        }
    }
};

const switchTab = (tab) => {
    type.value = tab;
    form.category_id = '';
    form.bucket_id = keepBucket(form.bucket_id, bucketChoices.value);
};

/** Only a change the user makes may drop the bucket; loading an edit never does. */
const accountChanged = () => {
    form.bucket_id = keepBucket(form.bucket_id, bucketChoices.value);
};

const routes = {
    'transactions.store': () => storeTransaction(),
    'transactions.update': () => updateTransaction(props.transactionId),
    'planned-events.store': () => storePlanned(),
    'planned-events.update': () => updatePlanned(props.event.id),
    'planned-series.update': () => updateSeries(props.seriesId),
    'planned-events.realize': () => realize(props.event.id),
};

const submit = (confirmFuture = false) => {
    form.confirm_future = confirmFuture;
    form.transform((data) => payload({ ...data, intent: props.intent, when: when.value, type: type.value, overrides }));
    form.submit(routes[submitTarget(props.intent, when.value)](), {
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    });
};

const planInstead = () => chooseWhen('planned');

defineExpose({ submit });

const fieldClass = 'mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200';
</script>

<template>
    <div>
        <div v-if="isCreate" class="-mt-2 mb-4 flex gap-1 border-b border-gray-200 dark:border-gray-700">
            <button
                v-for="tab in tabs"
                :key="tab.value"
                type="button"
                class="rounded-t-lg border-b-2 px-4 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-40"
                :class="type === tab.value
                    ? 'border-primary-600 text-primary-600 dark:text-primary-400'
                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                :disabled="tab.disabled"
                @click="switchTab(tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>

        <div v-if="canChooseWhen(intent)" class="mb-4 grid grid-cols-2 gap-2">
            <button
                v-for="option in [{ value: 'happened', label: $t('ui.transaction_modal.when_happened') }, { value: 'planned', label: $t('ui.transaction_modal.when_planned') }]"
                :key="option.value"
                type="button"
                class="rounded-lg border px-3 py-2 text-sm font-medium"
                :class="when === option.value
                    ? 'border-primary-600 bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400'
                    : 'border-gray-300 text-gray-600 dark:border-gray-600 dark:text-gray-300'"
                @click="chooseWhen(option.value)"
            >
                {{ option.label }}
            </button>
        </div>
        <p v-if="planned" class="-mt-2 mb-4 text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.transaction_modal.planned_hint') }}</p>

        <div v-if="loading && !isCreate" class="py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ $t('ui.common.loading') }}</div>

        <form v-show="!(loading && !isCreate)" id="money-movement-form" class="space-y-4" @submit.prevent="submit(false)">
            <template v-if="isTransfer">
                <div>
                    <InputLabel for="from_account_id" :value="$t('ui.transaction_modal.from_account')" />
                    <select id="from_account_id" v-model="form.from_account_id" :class="fieldClass">
                        <option value="">{{ $t('ui.transaction_modal.select_account') }}</option>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }} · {{ account.currency }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.from_account_id" />
                </div>
                <div>
                    <InputLabel for="to_account_id" :value="$t('ui.transaction_modal.to_account')" />
                    <select id="to_account_id" v-model="form.to_account_id" :class="fieldClass">
                        <option value="">{{ $t('ui.transaction_modal.select_account') }}</option>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }} · {{ account.currency }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.to_account_id" />
                </div>
            </template>

            <template v-else>
                <div>
                    <InputLabel for="account_id" :value="$t('ui.transaction_modal.account')" />
                    <select id="account_id" v-model="form.account_id" :class="fieldClass" @change="accountChanged">
                        <option value="">{{ planned ? $t('ui.transaction_modal.any_account') : $t('ui.transaction_modal.select_account') }}</option>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }} · {{ account.currency }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.account_id" />
                </div>
                <div>
                    <InputLabel for="category_id" :value="$t('ui.transaction_modal.category')" />
                    <select id="category_id" v-model="form.category_id" :class="fieldClass">
                        <option value="">{{ planned ? $t('ui.transaction_modal.none_yet') : $t('ui.transaction_modal.select_category') }}</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.category_id" />
                </div>
            </template>

            <div class="grid grid-cols-1 gap-4" :class="planned ? 'sm:grid-cols-2' : 'sm:grid-cols-3'">
                <div>
                    <InputLabel for="amount" :value="$t('ui.transaction_modal.amount')" />
                    <TextInput id="amount" v-model="form.amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.amount" />
                </div>
                <div v-if="intent === 'editSeries'">
                    <InputLabel for="next_date" :value="$t('ui.transaction_modal.next_date')" />
                    <TextInput id="next_date" v-model="form.next_date" type="date" :min="today" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.next_date" />
                </div>
                <div v-else>
                    <InputLabel for="date" :value="$t('ui.transaction_modal.date')" />
                    <TextInput id="date" v-model="form.date" type="date" class="mt-1 block w-full" :disabled="intent === 'editPlanned' && form.scope === 'future'" />
                    <InputError v-if="!prompt && !futureEdit" class="mt-1" :message="form.errors.date" />
                </div>
                <div v-if="!planned">
                    <InputLabel for="time" :value="$t('ui.transaction_modal.time')" />
                    <TextInput id="time" v-model="form.time" type="time" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.time" />
                </div>
            </div>

            <div v-if="prompt === 'suggest_planned' || futureEdit" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                <p>{{ $t('ui.transaction_modal.future_prompt') }}</p>
                <div class="mt-2 flex flex-wrap gap-3 font-semibold">
                    <button v-if="isCreate" type="button" class="hover:underline" @click="planInstead">{{ $t('ui.transaction_modal.plan_instead') }}</button>
                    <button type="button" class="hover:underline" :disabled="form.processing" @click="submit(true)">{{ $t('ui.transaction_modal.it_happened') }}</button>
                </div>
            </div>
            <p v-else-if="prompt === 'suggest_happened'" class="text-xs text-gray-500 dark:text-gray-400">
                {{ $t('ui.transaction_modal.past_planned_hint') }}
                <button type="button" class="font-semibold text-primary-600 hover:underline dark:text-primary-400" @click="chooseWhen('happened')">{{ $t('ui.transaction_modal.record_happened') }}</button>
            </p>

            <div v-if="!planned && type === 'expense'">
                <InputLabel for="payment_method" :value="$t('ui.transaction_modal.payment_method')" />
                <select id="payment_method" v-model="form.payment_method" :class="fieldClass">
                    <option value="">{{ $t('ui.transaction_modal.unspecified') }}</option>
                    <option value="cash">{{ $t('ui.transaction_modal.cash') }}</option>
                    <option value="debit_card">{{ $t('ui.transaction_modal.debit_card') }}</option>
                    <option value="credit_card">{{ $t('ui.transaction_modal.credit_card') }}</option>
                    <option value="bank_transfer">{{ $t('ui.transaction_modal.bank_transfer') }}</option>
                    <option value="other">{{ $t('ui.transaction_modal.other') }}</option>
                </select>
            </div>

            <div v-if="bucketChoices.length || form.errors.bucket_id">
                <InputLabel for="bucket_id" :value="$t('ui.transaction_modal.pay_from_bucket')" />
                <select id="bucket_id" v-model="form.bucket_id" :class="fieldClass">
                    <option value="">{{ $t('ui.transaction_modal.no_bucket') }}</option>
                    <option v-for="bucket in bucketChoices" :key="bucket.id" :value="bucket.id">
                        {{ bucket.name }} · {{ format.money(bucket.available, bucket.currency) }}{{ bucket.current ? ` · ${$t('ui.transaction_modal.current_bucket')}` : '' }}
                    </option>
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

            <div v-if="intent !== 'confirmPlanned'">
                <InputLabel for="notes" :value="$t('ui.transaction_modal.notes')" />
                <textarea id="notes" v-model="form.notes" rows="2" :class="fieldClass"></textarea>
                <InputError class="mt-1" :message="form.errors.notes" />
            </div>

            <template v-if="planned">
                <div>
                    <InputLabel for="certainty" :value="$t('ui.transaction_modal.certainty')" />
                    <SelectInput id="certainty" v-model="form.certainty">
                        <option v-for="certainty in plannedOptions.certainties" :key="certainty.value" :value="certainty.value">{{ certainty.label }}</option>
                    </SelectInput>
                    <InputError class="mt-1" :message="form.errors.certainty" />
                </div>

                <div v-if="isCreate || intent === 'editSeries'" class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="frequency" :value="$t('ui.transaction_modal.repeat')" />
                        <SelectInput id="frequency" v-model="form.frequency">
                            <option v-if="isCreate" value="">{{ $t('ui.transaction_modal.no_repeat') }}</option>
                            <option v-for="frequency in plannedOptions.frequencies" :key="frequency.value" :value="frequency.value">{{ frequency.label }}</option>
                        </SelectInput>
                        <InputError class="mt-1" :message="form.errors.frequency" />
                    </div>
                    <div>
                        <InputLabel for="ends_on" :value="$t('ui.transaction_modal.until')" />
                        <TextInput id="ends_on" v-model="form.ends_on" type="date" class="mt-1 block w-full" :disabled="!repeats" />
                        <InputError class="mt-1" :message="form.errors.ends_on" />
                    </div>
                </div>

                <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-200">
                    <input v-model="form.auto_post" type="checkbox" class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700" />
                    <span>
                        {{ $t('ui.transaction_modal.auto_post') }}
                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.transaction_modal.auto_post_hint') }}</span>
                    </span>
                </label>

                <details v-if="type === 'income' && splitBuckets.length" class="rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700">
                    <summary class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-200">{{ $t('ui.transaction_modal.where_income') }}</summary>
                    <p class="mb-2 mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.transaction_modal.where_income_hint') }}</p>
                    <div v-for="bucket in splitBuckets" :key="bucket.id" class="mb-1.5 flex items-center justify-between gap-3">
                        <span class="text-sm text-gray-700 dark:text-gray-200">{{ bucket.name }} <span class="text-xs text-gray-400">{{ bucket.currency }}</span></span>
                        <TextInput v-model="overrides[bucket.id]" type="number" step="0.01" min="0" class="block w-32" placeholder="0" />
                    </div>
                    <InputError class="mt-1" :message="form.errors['allocation_overrides.0.bucket_id'] || form.errors['allocation_overrides.0.amount']" />
                </details>

                <fieldset v-if="intent === 'editPlanned' && event?.seriesId" class="space-y-2 rounded-lg bg-gray-50 px-4 py-3 text-sm dark:bg-gray-900/40">
                    <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $t('ui.transaction_modal.this_repeats') }}</legend>
                    <label class="flex items-center gap-2 text-gray-700 dark:text-gray-200">
                        <input v-model="form.scope" type="radio" value="this" class="text-primary-600" /> {{ $t('ui.transaction_modal.only_this') }}
                    </label>
                    <label class="flex items-center gap-2 text-gray-700 dark:text-gray-200">
                        <input v-model="form.scope" type="radio" value="future" class="text-primary-600" /> {{ $t('ui.transaction_modal.this_and_later') }}
                    </label>
                    <button type="button" class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400" @click="emit('edit-series', event.seriesId)">
                        {{ $t('ui.transaction_modal.edit_whole_series') }}
                    </button>
                </fieldset>
            </template>
        </form>
    </div>
</template>
