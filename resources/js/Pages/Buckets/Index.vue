<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
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
import { formatMoney } from '@/chartPalette';
import { index as plannedEventsIndex } from '@/routes/planned-events';
import {
    store,
    update,
    destroy,
    move,
    adjust,
    reset,
    complete,
    reopen,
    history,
    allocations,
    destroyAllocation,
    bulkDestroy,
} from '@/actions/App/Modules/Planning/Http/Controllers/BucketController';
import { useFormat } from '@/composables/useFormat';
import BulkCheckbox from '@/Components/BulkCheckbox.vue';
import BulkDeleteBar from '@/Components/BulkDeleteBar.vue';
import { useBulkSelection } from '@/composables/useBulkSelection';

const format = useFormat();
const { t } = useI18n();

const props = defineProps({
    buckets: { type: Array, required: true },
    categories: { type: Array, required: true },
    accounts: { type: Array, required: true },
    currencies: { type: Array, required: true },
    kinds: { type: Array, required: true },
    cycles: { type: Array, required: true },
    fillRules: { type: Array, required: true },
    carryOvers: { type: Array, required: true },
});

const bulk = useBulkSelection(() => props.buckets);
watch(() => props.buckets, () => bulk.clear());

const page = usePage();
const locale = () => page.props.auth.user?.locale ?? 'en';
const money = (value, currency) => formatMoney(value, currency, locale());
const formatDate = (date) => format.date(date, { year: false });

const barColor = (status) => ({ exceeded: 'bg-red-500', near_limit: 'bg-orange-400', on_track: 'bg-green-500' }[status] ?? 'bg-green-500');

const ruleText = (bucket) => {
    if (!bucket.fillRule) return t('ui.buckets.rule_none');
    if (bucket.fillRule === 'percent') return t('ui.buckets.rule_percent', { value: bucket.fillValue });
    if (bucket.fillRule === 'of_remaining') return t('ui.buckets.rule_remaining', { value: bucket.fillValue });
    if (bucket.fillRule === 'fixed') {
        return t(bucket.cycle ? 'ui.buckets.rule_fixed_cycle' : 'ui.buckets.rule_fixed', { amount: money(bucket.fillValue, bucket.currency) });
    }
    return t('ui.buckets.rule_target');
};

const targetPercent = (bucket) => (bucket.cap ? Math.max(0, Math.min(100, (bucket.available / bucket.cap) * 100)) : 0);

/* ---- create / edit ---- */
const showModal = ref(false);
const editingBucket = ref(null);

const blank = () => ({
    kind: 'spending',
    name: '',
    currency: props.currencies[0],
    category_id: '',
    cycle: 'monthly',
    cycle_every: 1,
    cycle_anchor: '',
    cycle_goal: '',
    cap_amount: '',
    priority: '',
    opening_balance: '',
    fill_rule: '',
    fill_value: '',
    step_up_after_bucket_id: '',
    step_up_value: '',
    completes_on_purchase: false,
    carry_over: 'carry',
    overflow_bucket_id: '',
    allowed_account_ids: [],
    is_default: false,
});

const form = useForm(blank());
const isSpending = computed(() => form.kind === 'spending');
// A spending bucket always has a cycle; a savings bucket may have none.
watch(() => form.kind, (kind) => {
    if (kind === 'spending' && !form.cycle) form.cycle = 'monthly';
    if (kind === 'savings' && !editingBucket.value) form.cycle = '';
});
const needsValue = computed(() => ['fixed', 'percent', 'of_remaining'].includes(form.fill_rule));
const fillValueLabel = computed(() => {
    if (form.fill_rule === 'percent') return t('ui.buckets.fill_percent');
    if (form.fill_rule === 'of_remaining') return t('ui.buckets.fill_remaining');
    return t(form.cycle ? 'ui.buckets.fill_cycle' : 'ui.buckets.fill_each');
});
const canStepUp = computed(() => needsValue.value);
const stepUpChoices = computed(() => props.buckets.filter((bucket) => bucket.currency === form.currency && bucket.id !== editingBucket.value?.id && bucket.cap && bucket.kind === 'savings'));
const overflowChoices = computed(() => props.buckets.filter((bucket) => bucket.currency === form.currency && bucket.id !== editingBucket.value?.id));

const openCreateModal = () => {
    editingBucket.value = null;
    form.defaults(blank());
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (bucket) => {
    editingBucket.value = bucket;
    form.clearErrors();
    form.kind = bucket.kind;
    form.name = bucket.name;
    form.currency = bucket.currency;
    form.category_id = bucket.category?.id ?? '';
    form.cycle = bucket.cycle ?? '';
    form.cycle_every = bucket.cycleEvery ?? 1;
    form.cycle_anchor = bucket.cycleAnchor ?? '';
    form.cycle_goal = bucket.cycleGoal !== null && bucket.cycleGoal !== undefined ? String(bucket.cycleGoal) : '';
    form.cap_amount = bucket.cap !== null ? String(bucket.cap) : '';
    form.priority = String(bucket.priority);
    form.opening_balance = bucket.openingBalance > 0 ? String(bucket.openingBalance) : '';
    form.fill_rule = bucket.fillRule ?? '';
    form.fill_value = bucket.fillValue !== null ? String(bucket.fillValue) : '';
    form.step_up_after_bucket_id = bucket.stepUpAfterBucketId ?? '';
    form.step_up_value = bucket.stepUpValue !== null ? String(bucket.stepUpValue) : '';
    form.completes_on_purchase = bucket.completesOnPurchase;
    form.carry_over = bucket.carryOver;
    form.overflow_bucket_id = bucket.overflowBucketId ?? '';
    form.allowed_account_ids = [...bucket.allowedAccountIds];
    form.is_default = bucket.isDefault;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const submit = () => {
    const action = editingBucket.value ? update(editingBucket.value.id) : store();
    form.submit(action, { preserveScroll: true, onSuccess: () => closeModal() });
};

/* ---- move / adjust / delete ---- */
const movingFrom = ref(null);
const moveForm = useForm({ to_bucket_id: '', amount: '', description: '' });
const moveTargets = computed(() => props.buckets.filter((bucket) => movingFrom.value && bucket.currency === movingFrom.value.currency && bucket.id !== movingFrom.value.id));

const openMove = (bucket) => {
    movingFrom.value = bucket;
    moveForm.reset();
    moveForm.clearErrors();
};

const submitMove = () => {
    moveForm.submit(move(movingFrom.value.id), { preserveScroll: true, onSuccess: () => (movingFrom.value = null) });
};

/* ---- full history: every allocation, removable when entered by hand ---- */
const historyBucket = ref(null);
const historyRows = ref([]);
const historyLoading = ref(false);

const loadHistory = async () => {
    historyLoading.value = true;
    try {
        const response = await fetch(allocations.url(historyBucket.value.id), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        historyRows.value = (await response.json()).allocations;
    } finally {
        historyLoading.value = false;
    }
};

const openHistory = (bucket) => {
    historyBucket.value = bucket;
    historyRows.value = [];
    loadHistory();
};

const removeEntry = (row) => {
    router.delete(destroyAllocation.url({ bucket: historyBucket.value.id, allocation: row.id }), { preserveScroll: true, onSuccess: loadHistory });
};

const resetToZero = () => {
    router.post(reset.url(historyBucket.value.id), {}, { preserveScroll: true, onSuccess: loadHistory });
};

const adjusting = ref(null);
const adjustForm = useForm({ amount: '', description: '' });

const openAdjust = (bucket) => {
    adjusting.value = bucket;
    adjustForm.reset();
    adjustForm.clearErrors();
};

const submitAdjust = () => {
    adjustForm.submit(adjust(adjusting.value.id), { preserveScroll: true, onSuccess: () => (adjusting.value = null) });
};

const confirmingDelete = ref(null);
const deleteBucket = () => {
    router.delete(destroy.url(confirmingDelete.value.id), {
        preserveScroll: true,
        onFinish: () => (confirmingDelete.value = null),
    });
};
</script>

<template>
    <Head :title="$t('ui.buckets.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">{{ $t('ui.buckets.title') }}</h2>
                <PrimaryButton type="button" @click="openCreateModal">
                    <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                    {{ $t('ui.buckets.new') }}
                </PrimaryButton>
            </div>
        </template>

        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <p v-if="buckets.length" class="mb-6 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                {{ $t('ui.buckets.intro') }}
            </p>

            <EmptyState
                v-if="buckets.length === 0"
                icon="chart-pie"
                :title="$t('ui.buckets.empty_title')"
                :description="$t('ui.buckets.empty_hint')"
            >
                <template #action>
                    <PrimaryButton type="button" @click="openCreateModal">
                        <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                        {{ $t('ui.buckets.create_first') }}
                    </PrimaryButton>
                </template>
            </EmptyState>

            <div v-else>
            <div class="mb-3 space-y-2">
                <label class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400"><BulkCheckbox :bulk="bulk" page /> {{ $t('ui.buckets.select_all') }}</label>
                <BulkDeleteBar :bulk="bulk" :url="bulkDestroy.url()" :noun="$t('ui.buckets.noun')" :note="$t('ui.buckets.bulk_note')" />
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div
                    v-for="bucket in buckets"
                    :key="bucket.id"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <BulkCheckbox :bulk="bulk" :id="bucket.id" :label="$t('ui.buckets.select_one', { name: bucket.name })" />
                            <CategoryIcon v-if="bucket.category" :category="bucket.category" size="h-10 w-10" />
                            <div v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-500/10">
                                <DynamicIcon name="banknotes" class="h-5 w-5" />
                            </div>
                            <div class="min-w-0">
                                <h3 class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ bucket.name }}</h3>
                                <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-gray-400">
                                    <span>{{ bucket.kindLabel }}</span>
                                    <span v-if="bucket.cycleLabel">&middot; {{ bucket.cycleLabel }}</span>
                                    <span class="rounded-full bg-gray-100 px-1.5 py-0.5 font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-300" :title="$t('ui.buckets.priority_hint')">P{{ bucket.priority }}</span>
                                    <span v-if="bucket.isDefault" class="rounded-full bg-primary-50 px-1.5 py-0.5 font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-400" :title="$t('ui.buckets.default_hint')">{{ $t('ui.buckets.default') }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-0.5">
                            <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" :title="$t('ui.buckets.move_money')" @click="openMove(bucket)">
                                <DynamicIcon name="arrows-right-left" class="h-4 w-4" />
                            </button>
                            <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" :title="$t('ui.buckets.add_remove')" @click="openAdjust(bucket)">
                                <DynamicIcon name="banknotes" class="h-4 w-4" />
                            </button>
                            <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" :title="$t('ui.buckets.edit_tip')" @click="openEditModal(bucket)">
                                <DynamicIcon name="pencil" class="h-4 w-4" />
                            </button>
                            <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" :title="$t('ui.common.delete')" @click="confirmingDelete = bucket">
                                <DynamicIcon name="trash" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div v-if="bucket.hasFunding" class="mt-4">
                        <p class="text-xs uppercase tracking-wide text-gray-400">{{ $t('ui.buckets.available') }}</p>
                        <p class="text-2xl font-semibold tabular-nums" :class="bucket.available < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'">
                            <Money :amount="bucket.available" :currency="bucket.currency" />
                        </p>
                        <template v-if="bucket.kind === 'savings' && bucket.cap">
                            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                <div class="h-full bg-primary-500 transition-all" :style="{ width: targetPercent(bucket) + '%' }"></div>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">
                                {{ $t('ui.buckets.percent_of', { percent: format.number(targetPercent(bucket), 0) }) }} <Money :amount="bucket.cap" :currency="bucket.currency" />
                            </p>
                        </template>
                    </div>

                    <div v-if="bucket.tracksLimit" class="mt-4">
                        <div class="mb-1 flex items-baseline justify-between text-sm">
                            <span class="font-semibold text-gray-900 dark:text-white"><Money :amount="bucket.spent" :currency="bucket.currency" /></span>
                            <span class="text-gray-400">{{ $t('ui.buckets.spent_of') }} <Money :amount="bucket.cap" :currency="bucket.currency" /></span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                            <div class="h-full transition-all" :class="barColor(bucket.status)" :style="{ width: Math.min(bucket.percentage, 100) + '%' }"></div>
                        </div>
                        <div class="mt-1.5 flex items-center justify-between text-xs">
                            <span class="text-gray-400">{{ $t('ui.buckets.percent_used', { percent: format.number(bucket.percentage, 0) }) }}</span>
                            <span v-if="bucket.status === 'exceeded'" class="font-medium text-red-600 dark:text-red-400">{{ $t('ui.buckets.limit_exceeded') }}</span>
                            <span v-else-if="bucket.status === 'near_limit'" class="font-medium text-orange-500">{{ $t('ui.buckets.near_limit') }}</span>
                            <span v-else class="text-gray-400"><Money :amount="bucket.remaining" :currency="bucket.currency" /> {{ $t('ui.buckets.left') }}</span>
                        </div>
                        <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3 text-xs dark:border-gray-700">
                            <button
                                type="button"
                                class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                @click="router.visit(plannedEventsIndex.url())"
                            >
                                <template v-if="bucket.projected > 0"><Money :amount="bucket.projected" :currency="bucket.currency" /> {{ $t('ui.buckets.planned') }}</template>
                                <template v-else>{{ $t('ui.buckets.plan_spending') }}</template>
                            </button>
                            <span v-if="bucket.projected > 0" class="text-gray-400">
                                {{ $t('ui.buckets.after_plans') }} <Money :amount="bucket.effectiveRemaining" :currency="bucket.currency" />
                            </span>
                        </div>
                    </div>

                    <ul class="mt-4 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                        <li class="flex items-center gap-1.5"><DynamicIcon name="arrow-down-circle" class="h-3.5 w-3.5" /> {{ ruleText(bucket) }}</li>
                        <li v-if="bucket.cycle" class="flex items-center gap-1.5">
                            <DynamicIcon name="arrow-path" class="h-3.5 w-3.5" /> {{ $t('ui.buckets.cycle_end', { description: bucket.cycleDescription, policy: bucket.carryOverLabel.toLowerCase() }) }}
                        </li>
                        <li v-if="bucket.cycleView && (bucket.cycleView.provisional > 0 || bucket.cycleView.projectedIn > 0 || bucket.cycleView.projectedRelease > 0)" class="rounded-lg bg-gray-50 px-2.5 py-2 italic dark:bg-gray-900/40">
                            {{ $t('ui.buckets.expected', { date: formatDate(bucket.cycleView.end) }) }}
                            <template v-if="bucket.cycleView.provisional > 0">+<Money :amount="bucket.cycleView.provisional" :currency="bucket.currency" /> {{ $t('ui.buckets.from_planned') }} </template>
                            <template v-if="bucket.cycleView.projectedRelease > 0"><Money :amount="bucket.cycleView.projectedRelease" :currency="bucket.currency" /> {{ $t('ui.buckets.moves_on') }} </template>
                            <template v-if="bucket.cycleView.projectedIn > 0">+<Money :amount="bucket.cycleView.projectedIn" :currency="bucket.currency" /> {{ $t('ui.buckets.overflow_arriving') }}</template>
                        </li>
                        <li v-if="bucket.cycleView && bucket.cycleGoal" class="flex items-center gap-1.5">
                            <DynamicIcon name="flag" class="h-3.5 w-3.5" /> {{ $t('ui.buckets.this_cycle') }} <Money :amount="bucket.cycleView.receivedSoFar" :currency="bucket.currency" /> {{ $t('ui.common.of') }} <Money :amount="bucket.cycleGoal" :currency="bucket.currency" /> {{ $t('ui.buckets.goal') }}
                        </li>
                        <li v-if="bucket.cycleView && bucket.cycleView.capOverride" class="text-amber-600 dark:text-amber-400">
                            {{ $t('ui.buckets.limit_only') }} <Money :amount="bucket.cycleView.capOverride" :currency="bucket.currency" />
                        </li>
                        <li v-if="bucket.completedOn" class="flex items-center gap-1.5 text-green-700 dark:text-green-400">
                            <DynamicIcon name="check-circle" class="h-3.5 w-3.5" /> {{ $t('ui.buckets.completed_on', { date: formatDate(bucket.completedOn) }) }}
                            <button type="button" class="font-medium underline" @click="router.post(reopen.url(bucket.id), {}, { preserveScroll: true })">{{ $t('ui.buckets.reopen') }}</button>
                        </li>
                        <li v-else-if="bucket.kind === 'savings' && !bucket.isDefault">
                            <button type="button" class="font-medium text-primary-600 hover:underline dark:text-primary-400" @click="router.post(complete.url(bucket.id), {}, { preserveScroll: true })">{{ $t('ui.buckets.mark_completed') }}</button>
                        </li>
                        <li v-if="bucket.cycle">
                            <Link :href="history.url(bucket.id)" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $t('ui.buckets.cycle_history') }}</Link>
                        </li>
                        <li v-if="bucket.allowedAccounts.length" class="flex flex-wrap items-center gap-1.5">
                            <DynamicIcon name="wallet" class="h-3.5 w-3.5" /> {{ $t('ui.buckets.only_from') }}
                            <span v-for="account in bucket.allowedAccounts" :key="account.id" class="rounded-full bg-gray-100 px-2 py-0.5 dark:bg-gray-700">{{ account.name }}</span>
                        </li>
                    </ul>

                    <details v-if="bucket.recentAllocations.length" class="mt-3 text-xs">
                        <summary class="cursor-pointer font-medium text-gray-500 dark:text-gray-400">{{ $t('ui.buckets.recent') }}</summary>
                        <button type="button" class="mt-2 text-primary-600 hover:underline dark:text-primary-400" @click="openHistory(bucket)">{{ $t('ui.buckets.all_activity_link') }}</button>
                        <ul class="mt-2 divide-y divide-gray-100 dark:divide-gray-700">
                            <li v-for="row in bucket.recentAllocations" :key="row.id" class="flex items-center justify-between py-1.5">
                                <span class="text-gray-500 dark:text-gray-400">{{ formatDate(row.date) }} &middot; {{ row.description || row.sourceLabel }}</span>
                                <span class="tabular-nums" :class="row.amount < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'">
                                    <Money :amount="row.amount" :currency="bucket.currency" signed />
                                </span>
                            </li>
                        </ul>
                    </details>
                </div>
            </div>
            </div>
        </div>

        <ModalForm :show="showModal" :title="editingBucket ? $t('ui.buckets.edit') : $t('ui.buckets.new')" @close="closeModal">
            <form id="bucket-form" class="space-y-4" @submit.prevent="submit">
                <SegmentedControl v-model="form.kind" :options="kinds" />
                <p class="-mt-2 text-xs text-gray-500 dark:text-gray-400">
                    <template v-if="isSpending">{{ $t('ui.buckets.spending_hint') }}</template>
                    <template v-else>{{ $t('ui.buckets.savings_hint') }}</template>
                </p>
                <InputError :message="form.errors.kind" />

                <div v-if="isSpending">
                    <InputLabel for="category_id" :value="$t('ui.buckets.category')" />
                    <SelectInput id="category_id" v-model="form.category_id">
                        <option value="">{{ $t('ui.buckets.select_category') }}</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                    </SelectInput>
                    <InputError class="mt-1" :message="form.errors.category_id" />
                </div>

                <div>
                    <InputLabel for="name" :value="$t(isSpending ? 'ui.buckets.name_optional' : 'ui.buckets.name')" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" :placeholder="$t(isSpending ? 'ui.buckets.name_placeholder_spending' : 'ui.buckets.name_placeholder_savings')" />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="cap_amount" :value="$t(isSpending ? 'ui.buckets.limit' : 'ui.buckets.target_optional')" />
                        <TextInput id="cap_amount" v-model="form.cap_amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="form.errors.cap_amount" />
                    </div>
                    <div>
                        <InputLabel for="currency" :value="$t('ui.buckets.currency')" />
                        <SelectInput id="currency" v-model="form.currency" :disabled="editingBucket !== null">
                            <option v-for="currency in currencies" :key="currency" :value="currency">{{ currency }}</option>
                        </SelectInput>
                        <InputError class="mt-1" :message="form.errors.currency" />
                    </div>
                </div>

                <div>
                    <InputLabel for="cycle_every" :value="$t(isSpending ? 'ui.buckets.cycle_label' : 'ui.buckets.cycle_label_optional')" />
                    <div class="mt-1 flex gap-2">
                        <TextInput id="cycle_every" v-model="form.cycle_every" type="number" min="1" max="366" class="block w-20" />
                        <SelectInput id="cycle" v-model="form.cycle">
                            <option v-if="!isSpending" value="">{{ $t('ui.buckets.no_cycle') }}</option>
                            <option value="daily">{{ $t(Number(form.cycle_every) === 1 ? 'ui.buckets.day' : 'ui.buckets.days') }}</option>
                            <option value="weekly">{{ $t(Number(form.cycle_every) === 1 ? 'ui.buckets.week' : 'ui.buckets.weeks') }}</option>
                            <option value="monthly">{{ $t(Number(form.cycle_every) === 1 ? 'ui.buckets.month' : 'ui.buckets.months') }}</option>
                            <option value="yearly">{{ $t(Number(form.cycle_every) === 1 ? 'ui.buckets.year' : 'ui.buckets.years') }}</option>
                        </SelectInput>
                    </div>
                    <InputError class="mt-1" :message="form.errors.cycle || form.errors.cycle_every" />
                    <template v-if="!isSpending && form.cycle">
                        <InputLabel for="cycle_goal" class="mt-3" :value="$t('ui.buckets.cycle_goal')" />
                        <TextInput id="cycle_goal" v-model="form.cycle_goal" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :placeholder="$t('ui.buckets.cycle_goal_placeholder')" />
                        <InputError class="mt-1" :message="form.errors.cycle_goal" />
                    </template>
                    <InputLabel for="cycle_anchor" class="mt-3" :value="$t('ui.buckets.starting_on')" />
                    <TextInput id="cycle_anchor" v-model="form.cycle_anchor" type="date" class="mt-1 block w-full" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $t('ui.buckets.starting_on_hint') }}
                    </p>
                    <InputError class="mt-1" :message="form.errors.cycle_anchor" />
                </div>

                <details class="rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700" :open="Boolean(form.fill_rule) || form.is_default">
                    <summary class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-200">{{ $t('ui.buckets.advanced') }}</summary>
                    <div class="mt-3 space-y-4">
                        <div>
                            <InputLabel for="fill_rule" :value="$t('ui.buckets.fill_rule')" />
                            <SelectInput id="fill_rule" v-model="form.fill_rule">
                                <option value="">{{ $t('ui.buckets.fill_none') }}</option>
                                <option v-for="rule in fillRules" :key="rule.value" :value="rule.value">{{ rule.label }}</option>
                            </SelectInput>
                            <InputError class="mt-1" :message="form.errors.fill_rule" />
                        </div>

                        <div v-if="needsValue">
                            <InputLabel for="fill_value" :value="fillValueLabel" />
                            <TextInput id="fill_value" v-model="form.fill_value" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.fill_value" />
                        </div>
                        <div v-if="canStepUp && stepUpChoices.length" class="rounded-lg bg-gray-50 p-3 dark:bg-gray-700/40">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $t('ui.buckets.step_up') }}</p>
                            <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.buckets.step_up_hint') }}</p>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <InputLabel for="step_up_after_bucket_id" :value="$t('ui.buckets.step_up_when')" />
                                    <SelectInput id="step_up_after_bucket_id" v-model="form.step_up_after_bucket_id">
                                        <option value="">{{ $t('ui.buckets.never') }}</option>
                                        <option v-for="bucket in stepUpChoices" :key="bucket.id" :value="bucket.id">{{ bucket.name }}</option>
                                    </SelectInput>
                                    <InputError class="mt-1" :message="form.errors.step_up_after_bucket_id" />
                                </div>
                                <div>
                                    <InputLabel for="step_up_value" :value="$t('ui.buckets.step_up_value')" />
                                    <TextInput id="step_up_value" v-model="form.step_up_value" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :disabled="!form.step_up_after_bucket_id" />
                                    <InputError class="mt-1" :message="form.errors.step_up_value" />
                                </div>
                            </div>
                        </div>
                        <label v-if="form.kind === 'savings'" class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input v-model="form.completes_on_purchase" type="checkbox" class="mt-0.5 rounded border-gray-300 text-primary-600 dark:border-gray-600 dark:bg-gray-700" />
                            <span>{{ $t('ui.buckets.completes') }}</span>
                        </label>
                        <p v-if="form.fill_rule === 'fill_to_target'" class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.buckets.needs_target') }}</p>

                        <div>
                            <InputLabel for="priority" :value="$t('ui.buckets.priority')" />
                            <TextInput id="priority" v-model="form.priority" type="number" min="0" max="9999" class="mt-1 block w-full" placeholder="100" />
                            <InputError class="mt-1" :message="form.errors.priority" />
                        </div>

                        <div>
                            <InputLabel for="opening_balance" :value="$t('ui.buckets.opening')" />
                            <TextInput id="opening_balance" v-model="form.opening_balance" type="number" step="0.01" min="0" class="mt-1 block w-full" placeholder="0" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.buckets.opening_hint') }}</p>
                            <InputError class="mt-1" :message="form.errors.opening_balance" />
                        </div>

                        <div>
                            <InputLabel for="carry_over" :value="$t('ui.buckets.leftover')" />
                            <SelectInput id="carry_over" v-model="form.carry_over">
                                <option v-for="policy in carryOvers" :key="policy.value" :value="policy.value">{{ policy.label }}</option>
                            </SelectInput>
                        </div>

                        <div v-if="form.carry_over === 'overflow'">
                            <InputLabel for="overflow_bucket_id" :value="$t('ui.buckets.move_leftover')" />
                            <SelectInput id="overflow_bucket_id" v-model="form.overflow_bucket_id">
                                <option value="">{{ $t('ui.buckets.select_bucket') }}</option>
                                <option v-for="bucket in overflowChoices" :key="bucket.id" :value="bucket.id">{{ bucket.name }}</option>
                            </SelectInput>
                            <InputError class="mt-1" :message="form.errors.overflow_bucket_id" />
                        </div>

                        <fieldset v-if="accounts.length">
                            <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('ui.buckets.only_accounts') }}</legend>
                            <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.buckets.only_accounts_hint') }}</p>
                            <label v-for="account in accounts" :key="account.id" class="flex items-center gap-2 py-0.5 text-sm text-gray-700 dark:text-gray-200">
                                <input v-model="form.allowed_account_ids" type="checkbox" :value="account.id" class="rounded border-gray-300 text-primary-600" />
                                {{ account.name }}
                            </label>
                            <InputError class="mt-1" :message="form.errors['allowed_account_ids.0']" />
                        </fieldset>

                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input v-model="form.is_default" type="checkbox" class="rounded border-gray-300 text-primary-600" />
                            {{ $t('ui.buckets.is_default') }}
                        </label>
                    </div>
                </details>
            </form>

            <template #footer>
                <SecondaryButton type="button" @click="closeModal">{{ $t('ui.common.cancel') }}</SecondaryButton>
                <PrimaryButton form="bucket-form" type="submit" :disabled="form.processing">{{ $t('ui.buckets.save') }}</PrimaryButton>
            </template>
        </ModalForm>

        <ModalForm
            :show="movingFrom !== null"
            :title="movingFrom ? $t('ui.buckets.move_from', { name: movingFrom.name }) : $t('ui.buckets.move_money')"
            :description="$t('ui.buckets.move_hint')"
            @close="movingFrom = null"
        >
            <form id="move-form" class="space-y-4" @submit.prevent="submitMove">
                <div>
                    <InputLabel for="to_bucket_id" :value="$t('ui.buckets.to')" />
                    <SelectInput id="to_bucket_id" v-model="moveForm.to_bucket_id">
                        <option value="">{{ $t('ui.buckets.select_bucket') }}</option>
                        <option v-for="bucket in moveTargets" :key="bucket.id" :value="bucket.id">{{ bucket.name }}</option>
                    </SelectInput>
                    <InputError class="mt-1" :message="moveForm.errors.to_bucket_id" />
                    <p v-if="movingFrom && moveTargets.length === 0" class="mt-1 text-xs text-gray-500">{{ $t('ui.buckets.need_another', { currency: movingFrom.currency }) }}</p>
                </div>
                <div>
                    <InputLabel for="move_amount" :value="movingFrom ? $t('ui.buckets.amount_available', { amount: money(movingFrom.available, movingFrom.currency) }) : $t('ui.buckets.amount')" />
                    <TextInput id="move_amount" v-model="moveForm.amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="moveForm.errors.amount" />
                </div>
                <div>
                    <InputLabel for="move_description" :value="$t('ui.buckets.note')" />
                    <TextInput id="move_description" v-model="moveForm.description" type="text" class="mt-1 block w-full" />
                </div>
            </form>
            <template #footer>
                <SecondaryButton type="button" @click="movingFrom = null">{{ $t('ui.common.cancel') }}</SecondaryButton>
                <PrimaryButton form="move-form" type="submit" :disabled="moveForm.processing">{{ $t('ui.buckets.move') }}</PrimaryButton>
            </template>
        </ModalForm>

        <ModalForm
            :show="adjusting !== null"
            :title="adjusting ? $t('ui.buckets.adjust_title', { name: adjusting.name }) : $t('ui.buckets.add_remove')"
            :description="$t('ui.buckets.adjust_hint')"
            @close="adjusting = null"
        >
            <form id="adjust-form" class="space-y-4" @submit.prevent="submitAdjust">
                <div>
                    <InputLabel for="adjust_amount" :value="$t('ui.buckets.amount')" />
                    <TextInput id="adjust_amount" v-model="adjustForm.amount" type="number" step="0.01" class="mt-1 block w-full" placeholder="250000 or -50000" />
                    <InputError class="mt-1" :message="adjustForm.errors.amount" />
                </div>
                <div>
                    <InputLabel for="adjust_description" :value="$t('ui.buckets.note')" />
                    <TextInput id="adjust_description" v-model="adjustForm.description" type="text" class="mt-1 block w-full" />
                </div>
            </form>
            <template #footer>
                <SecondaryButton type="button" @click="adjusting = null">{{ $t('ui.common.cancel') }}</SecondaryButton>
                <PrimaryButton form="adjust-form" type="submit" :disabled="adjustForm.processing">{{ $t('ui.buckets.apply') }}</PrimaryButton>
            </template>
        </ModalForm>

        <Modal :show="confirmingDelete !== null" @close="confirmingDelete = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $t('ui.buckets.delete_title') }}</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ $t('ui.buckets.delete_hint') }}
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="confirmingDelete = null">{{ $t('ui.common.cancel') }}</SecondaryButton>
                    <DangerButton type="button" @click="deleteBucket">{{ $t('ui.common.delete') }}</DangerButton>
                </div>
            </div>
        </Modal>

        <Modal :show="historyBucket !== null" max-width="lg" @close="historyBucket = null">
            <div v-if="historyBucket" class="p-6">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $t('ui.buckets.all_activity', { name: historyBucket.name }) }}</h2>
                    <SecondaryButton type="button" @click="resetToZero">{{ $t('ui.buckets.reset_zero') }}</SecondaryButton>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.buckets.history_hint') }}</p>
                <p v-if="historyLoading" class="mt-4 text-sm text-gray-400">{{ $t('ui.common.loading') }}</p>
                <ul v-else class="mt-3 max-h-96 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-700">
                    <li v-for="row in historyRows" :key="row.id" class="flex items-center justify-between gap-3 py-2 text-sm">
                        <span class="min-w-0 text-gray-600 dark:text-gray-300">
                            <span class="block truncate">{{ row.description || row.sourceLabel }}</span>
                            <span class="text-xs text-gray-400">{{ formatDate(row.date) }} &middot; {{ row.sourceLabel }}</span>
                        </span>
                        <span class="flex shrink-0 items-center gap-2">
                            <span class="tabular-nums" :class="row.amount < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'">
                                <Money :amount="row.amount" :currency="historyBucket.currency" signed />
                            </span>
                            <button v-if="row.deletable" type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" :title="$t('ui.buckets.remove_entry')" @click="removeEntry(row)">
                                <DynamicIcon name="trash" class="h-4 w-4" />
                            </button>
                        </span>
                    </li>
                    <li v-if="!historyRows.length" class="py-4 text-center text-sm text-gray-400">{{ $t('ui.buckets.no_activity') }}</li>
                </ul>
                <div class="mt-4 flex justify-end">
                    <SecondaryButton type="button" @click="historyBucket = null">{{ $t('ui.common.close') }}</SecondaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
