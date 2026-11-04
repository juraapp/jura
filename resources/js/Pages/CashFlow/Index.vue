<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CategoryIcon from '@/Components/CategoryIcon.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Modal from '@/Components/Modal.vue';
import Money from '@/Components/Money.vue';
import SeriesList from '@/Components/SeriesList.vue';
import NeedsReviewList from '@/Components/NeedsReviewList.vue';
import SegmentedControl from '@/Components/SegmentedControl.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { useDarkMode } from '@/composables/useDarkMode';
import { CHART_PALETTE, formatCompact, formatMoney } from '@/chartPalette';
import {
    index as plannedEventsIndex,
    destroy,
    skip,
    reopen,
    link,
    bulkDestroy,
} from '@/actions/App/Modules/Planning/Http/Controllers/PlannedEventController';
import { useTransactionModal } from '@/composables/useTransactionModal';
import { usePlanActions } from '@/composables/usePlanActions';
import { useFormat } from '@/composables/useFormat';
import BulkCheckbox from '@/Components/BulkCheckbox.vue';
import BulkDeleteBar from '@/Components/BulkDeleteBar.vue';
import { useBulkSelection } from '@/composables/useBulkSelection';
import { useToday } from '@/composables/useToday';

const format = useFormat();
const { t } = useI18n();

const props = defineProps({
    events: { type: Array, required: true },
    projection: { type: Array, required: true },
    view: { type: String, required: true },
    views: { type: Array, required: true },
    months: { type: Number, required: true },
    series: { type: Array, required: true },
    review: { type: Object, required: true },
    section: { type: String, default: null },
});

const { openPlanned } = useTransactionModal();

onMounted(() => {
    if (props.section) {
        document.getElementById(props.section)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});

const bulk = useBulkSelection(() => props.events);
watch(() => props.events, () => bulk.clear());

const page = usePage();
const { isDark } = useDarkMode();
const locale = () => page.props.auth.user?.locale ?? 'en';
const palette = () => CHART_PALETTE[isDark.value ? 'dark' : 'light'];
const todayDate = useToday();
const today = todayDate();

const viewHints = computed(() => ({
    conservative: t('ui.cash_flow.hint_conservative'),
    expected: t('ui.cash_flow.hint_expected'),
    optimistic: t('ui.cash_flow.hint_optimistic'),
}));

const changeView = (value) => {
    router.get(plannedEventsIndex.url({ query: { view: value, months: props.months } }), {}, { preserveScroll: true, preserveState: true });
};

const monthLabel = (month) => format.month(month);
const shortMonth = (month) => format.month(month, { short: true });
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
    chart: { height: 280, fontFamily: 'Roboto, sans-serif', toolbar: { show: false }, stacked: false },
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
const hasInterest = (currency) => currency.months.some((m) => m.interest > 0);
const chartSeries = (currency) => [
    { name: t('ui.cash_flow.s_income'), type: 'column', data: currency.months.map((m) => m.income) },
    { name: t('ui.cash_flow.s_expenses'), type: 'column', data: currency.months.map((m) => m.expense) },
    { name: t('ui.cash_flow.s_balance'), type: 'line', data: currency.months.map((m) => m.closing) },
];

const actions = usePlanActions();
const confirmEvent = actions.confirm;

/* "From plan" on a transaction links here with ?highlight=<plan id>. */
const highlighted = Number(new URLSearchParams(window.location.search).get('highlight')) || null;
onMounted(() => {
    if (highlighted) {
        document.getElementById(`plan-${highlighted}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});

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
    <Head :title="$t('ui.cash_flow.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">{{ $t('ui.cash_flow.title') }}</h2>
                <PrimaryButton type="button" @click="openPlanned(null, { type: 'expense' })">
                    <DynamicIcon name="plus" class="h-4 w-4" />
                    {{ $t('ui.cash_flow.add') }}
                </PrimaryButton>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
            <section v-if="review.count > 0 || section === 'review'" id="review" class="scroll-mt-20">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-red-600 dark:text-red-400">{{ $t('ui.cash_flow.review_title', { count: review.count }) }}</h3>
                <NeedsReviewList :review="review" />
            </section>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <SegmentedControl :model-value="view" :options="views" @update:model-value="changeView" />
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ viewHints[view] }}</p>
                </div>
                <p class="text-xs text-gray-400">{{ $t('ui.cash_flow.estimates') }}</p>
            </div>

            <section v-for="currency in projection" :key="currency.currency" class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                            {{ $t('ui.cash_flow.projected_balance', { currency: currency.currency }) }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $t('ui.cash_flow.today') }} <Money :amount="currency.opening" :currency="currency.currency" />
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
                                    <th class="py-2 pr-4 font-semibold">{{ $t('ui.cash_flow.month') }}</th>
                                    <th class="px-4 py-2 text-right font-semibold">{{ $t('ui.cash_flow.income') }}</th>
                                    <th class="px-4 py-2 text-right font-semibold">{{ $t('ui.cash_flow.expenses') }}</th>
                                    <th v-if="hasInterest(currency)" class="px-4 py-2 text-right font-semibold" :title="$t('ui.cash_flow.interest_hint')">{{ $t('ui.cash_flow.interest') }}</th>
                                    <th class="px-4 py-2 text-right font-semibold">{{ $t('ui.cash_flow.net') }}</th>
                                    <th class="py-2 pl-4 text-right font-semibold">{{ $t('ui.cash_flow.balance') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="row in currency.months" :key="row.month" class="tabular-nums text-gray-700 dark:text-gray-200">
                                    <td class="py-2 pr-4">{{ monthLabel(row.month) }}</td>
                                    <td class="px-4 py-2 text-right text-green-600 dark:text-green-400"><Money :amount="row.income" :currency="currency.currency" /></td>
                                    <td class="px-4 py-2 text-right text-red-600 dark:text-red-400"><Money :amount="row.expense" :currency="currency.currency" /></td>
                                    <td v-if="hasInterest(currency)" class="px-4 py-2 text-right italic text-green-600 dark:text-green-400"><Money :amount="row.interest" :currency="currency.currency" /></td>
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

            <section id="repeating" class="scroll-mt-20">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('ui.cash_flow.repeating_title') }}</h3>
                <SeriesList :series="series" />
            </section>

            <section>
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('ui.cash_flow.planned_events') }}</h3>

                <EmptyState
                    v-if="events.length === 0"
                    icon="calendar"
                    :title="$t('ui.cash_flow.empty_title')"
                    :description="$t('ui.cash_flow.empty_hint')"
                >
                    <template #action>
                        <PrimaryButton type="button" @click="openPlanned(null, { type: 'income' })">
                            <DynamicIcon name="plus" class="h-4 w-4" />
                            {{ $t('ui.cash_flow.add_first') }}
                        </PrimaryButton>
                    </template>
                </EmptyState>

                <div v-else class="space-y-6">
                    <label class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400"><BulkCheckbox :bulk="bulk" page /> {{ $t('ui.cash_flow.select_all') }}</label>
                    <BulkDeleteBar :bulk="bulk" :url="bulkDestroy.url()" :noun="$t('ui.cash_flow.noun')" :note="$t('ui.cash_flow.bulk_note')" />
                    <div v-for="group in groupedEvents" :key="group.month">
                        <h4 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ monthLabel(group.month) }}</h4>
                        <ul class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                            <li
                                v-for="event in group.items"
                                :id="`plan-${event.id}`"
                                :key="event.id"
                                class="px-4 py-3"
                                :class="{ 'opacity-60': event.status !== 'planned', 'ring-2 ring-inset ring-primary-400': event.id === highlighted }"
                            >
                                <div class="flex flex-wrap items-center gap-3">
                                    <BulkCheckbox :bulk="bulk" :id="event.id" :label="$t('ui.cash_flow.select_one', { name: event.description })" />
                                    <div class="w-14 shrink-0 text-center">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatDate(event.date) }}</p>
                                        <p v-if="isOverdue(event)" class="text-[10px] font-semibold uppercase text-red-600 dark:text-red-400">{{ $t('ui.cash_flow.overdue') }}</p>
                                    </div>

                                    <CategoryIcon v-if="event.category" :category="event.category" size="h-8 w-8" />
                                    <div v-else class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-700">
                                        <DynamicIcon :name="event.type === 'income' ? 'arrow-up-circle' : 'arrow-down-circle'" class="h-4 w-4" />
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                            {{ event.description }}
                                            <span v-if="event.seriesId" class="ml-1 text-xs font-normal text-gray-400" :title="$t('ui.cash_flow.repeats_tip')">
                                                <DynamicIcon name="arrow-path" class="inline h-3 w-3" /> {{ $t('ui.cash_flow.repeats') }}
                                            </span>
                                        </p>
                                        <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-gray-400">
                                            <span class="rounded-full px-2 py-0.5 font-medium" :class="certaintyClass(event.certainty)">{{ event.certaintyLabel }}</span>
                                            <span v-if="event.status !== 'planned'" class="rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ event.statusLabel }}</span>
                                            <span v-if="event.account">{{ event.account.name }}</span>
                                            <span v-if="event.autoPost && event.status === 'planned'" class="rounded-full bg-primary-50 px-2 py-0.5 font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">{{ $t('ui.cash_flow.auto') }}</span>
                                        </p>
                                        <p v-if="event.postError && event.status === 'planned'" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $t(`ui.cash_flow.post_error.${event.postError}`) }}</p>
                                    </div>

                                    <p
                                        class="text-right text-sm font-semibold tabular-nums"
                                        :class="event.type === 'income' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                    >
                                        {{ event.type === 'income' ? '+' : '-' }}<Money :amount="event.amount" :currency="event.currency" />
                                    </p>

                                    <div v-if="event.status === 'planned'" class="flex items-center gap-1">
                                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-green-50 hover:text-green-600 dark:hover:bg-green-500/10" :title="$t('ui.cash_flow.mark_done')" :aria-label="$t('ui.cash_flow.mark_done')" @click="confirmEvent(event)">
                                            <DynamicIcon name="check-circle" class="h-4 w-4" />
                                        </button>
                                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" :title="$t('ui.cash_flow.skip')" :aria-label="$t('ui.cash_flow.skip')" @click="skipEvent(event)">
                                            <DynamicIcon name="forward" class="h-4 w-4" />
                                        </button>
                                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" :title="$t('ui.cash_flow.edit')" :aria-label="$t('ui.cash_flow.edit')" @click="openPlanned(event)">
                                            <DynamicIcon name="pencil" class="h-4 w-4" />
                                        </button>
                                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" :title="$t('ui.common.delete')" :aria-label="$t('ui.common.delete')" @click="askDelete(event)">
                                            <DynamicIcon name="trash" class="h-4 w-4" />
                                        </button>
                                    </div>
                                    <div v-else-if="event.status === 'skipped'" class="flex items-center gap-1">
                                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" :title="$t('ui.cash_flow.reopen')" :aria-label="$t('ui.cash_flow.reopen')" @click="reopenEvent(event)">
                                            <DynamicIcon name="arrow-path" class="h-4 w-4" />
                                        </button>
                                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" :title="$t('ui.common.delete')" :aria-label="$t('ui.common.delete')" @click="askDelete(event)">
                                            <DynamicIcon name="trash" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>

                                <div
                                    v-if="event.suggestedMatch"
                                    class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-primary-50 px-3 py-2 text-xs text-primary-800 dark:bg-primary-500/10 dark:text-primary-300"
                                >
                                    <span>
                                        {{ $t('ui.cash_flow.looks_done', { description: event.suggestedMatch.description || $t('ui.cash_flow.a_transaction') }) }}
                                        (<Money :amount="event.suggestedMatch.amount" :currency="event.currency" />, {{ formatDate(event.suggestedMatch.date) }}, {{ event.suggestedMatch.account }})
                                    </span>
                                    <button type="button" class="inline-flex items-center gap-1 font-semibold hover:underline" @click="linkMatch(event)">
                                        <DynamicIcon name="link" class="h-3.5 w-3.5" />
                                        {{ $t('ui.cash_flow.link_it') }}
                                    </button>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
        </div>

        <Modal :show="confirmingDelete !== null" @close="confirmingDelete = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $t('ui.cash_flow.delete_title') }}</h2>

                <fieldset v-if="confirmingDelete && confirmingDelete.seriesId" class="mt-3 space-y-2 text-sm">
                    <label class="flex items-center gap-2 text-gray-700 dark:text-gray-200">
                        <input v-model="deleteScope" type="radio" value="this" class="text-primary-600" /> {{ $t('ui.cash_flow.only_this') }}
                    </label>
                    <label class="flex items-center gap-2 text-gray-700 dark:text-gray-200">
                        <input v-model="deleteScope" type="radio" value="future" class="text-primary-600" /> {{ $t('ui.cash_flow.this_future') }}
                    </label>
                </fieldset>
                <p v-else class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $t('ui.common.cannot_be_undone') }}</p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="confirmingDelete = null">{{ $t('ui.common.cancel') }}</SecondaryButton>
                    <DangerButton type="button" @click="deleteEvent">{{ $t('ui.common.delete') }}</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
