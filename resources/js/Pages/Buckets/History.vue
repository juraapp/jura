<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Money from '@/Components/Money.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useDarkMode } from '@/composables/useDarkMode';
import { useFormat } from '@/composables/useFormat';
import { CHART_PALETTE, formatCompact, formatMoney } from '@/chartPalette';
import { index as bucketsIndex, cycleLimit } from '@/actions/App/Modules/Planning/Http/Controllers/BucketController';

const props = defineProps({
    bucket: { type: Object, required: true },
    current: { type: Object, default: null },
    cycles: { type: Array, required: true },
});

const page = usePage();
const format = useFormat();
const { isDark } = useDarkMode();
const locale = () => page.props.auth.user?.locale ?? 'en';
const palette = () => CHART_PALETTE[isDark.value ? 'dark' : 'light'];

const range = (cycle) => `${format.date(cycle.start)} to ${format.date(cycle.end)}`;
const shortRange = (cycle) => format.date(cycle.start, { year: false });

// Oldest first on the chart, then the cycle still running (dashed values are estimates).
const timeline = computed(() => [...props.cycles].reverse().concat(props.current ? [props.current] : []));

const series = computed(() => [
    { name: 'Filled', type: 'column', data: timeline.value.map((cycle) => cycle.filled) },
    { name: 'Spent', type: 'column', data: timeline.value.map((cycle) => cycle.spent) },
    { name: 'Overflow out', type: 'column', data: timeline.value.map((cycle) => cycle.released) },
    { name: 'Left at the end', type: 'line', data: timeline.value.map((cycle) => cycle.closing) },
]);

const options = computed(() => ({
    chart: { type: 'line', height: 320, fontFamily: 'Roboto, sans-serif', toolbar: { show: false }, stacked: false },
    colors: [palette().income, palette().expense, palette().series[3], palette().savings],
    stroke: { width: [0, 0, 0, 3] },
    plotOptions: { bar: { columnWidth: '55%' } },
    xaxis: { categories: timeline.value.map((cycle) => `${shortRange(cycle)}${cycle.isOpen ? ' (now)' : ''}`), labels: { style: { colors: palette().text } } },
    yaxis: { labels: { style: { colors: palette().text }, formatter: (v) => formatCompact(v, locale()) } },
    grid: { borderColor: palette().grid },
    legend: { labels: { colors: Array(4).fill(palette().text), useSeriesColors: false } },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: (v) => formatMoney(v, props.bucket.currency, locale()) } },
}));

const limit = ref(props.current?.capOverride ?? '');
const saveLimit = (value) => router.post(cycleLimit.url(props.bucket.id), { cap_amount: value === '' ? null : value }, { preserveScroll: true });
</script>

<template>
    <Head :title="`${bucket.name} history`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">{{ bucket.name }}: cycle history</h2>
                    <p v-if="bucket.cycleDescription" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ bucket.cycleDescription }}. At the end: {{ bucket.carryOverLabel.toLowerCase() }}<template v-if="bucket.overflowBucket"> ({{ bucket.overflowBucket }})</template>.
                    </p>
                </div>
                <Link :href="bucketsIndex.url()" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">Back to buckets</Link>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
            <EmptyState
                v-if="!bucket.cycleDescription"
                icon="arrow-path"
                title="This bucket has no cycle"
                description="Give it a cycle (for example every month) in the bucket's settings to see how it fills and empties over time."
            />

            <template v-else>
                <section v-if="current" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Now: {{ range(current) }}</h3>
                    <dl class="mt-3 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                        <div><dt class="text-xs text-gray-400">Started with</dt><dd><Money :amount="current.opening" :currency="bucket.currency" /></dd></div>
                        <div><dt class="text-xs text-gray-400">Filled so far</dt><dd class="text-green-600 dark:text-green-400"><Money :amount="current.filled + current.movedIn" :currency="bucket.currency" /></dd></div>
                        <div><dt class="text-xs text-gray-400">Spent / moved out</dt><dd class="text-red-600 dark:text-red-400"><Money :amount="current.spent + current.movedOut" :currency="bucket.currency" /></dd></div>
                        <div>
                            <dt class="text-xs text-gray-400">Expected still to fill</dt>
                            <dd class="italic text-gray-500"><Money :amount="current.provisional" :currency="bucket.currency" /> (provisional)</dd>
                        </div>
                    </dl>
                    <p v-if="bucket.cycleGoal" class="mt-3 text-sm text-gray-700 dark:text-gray-200">
                        Goal this cycle: <Money :amount="bucket.cycleGoal" :currency="bucket.currency" />. Received so far:
                        <Money :amount="current.filled + current.movedIn" :currency="bucket.currency" />.
                    </p>
                    <p v-if="current.released > 0" class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        If you spend only what is planned, about <Money :amount="current.released" :currency="bucket.currency" /> moves on when this cycle ends.
                    </p>

                    <form v-if="bucket.kind === 'spending'" class="mt-4 flex flex-wrap items-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-700" @submit.prevent="saveLimit(limit)">
                        <div>
                            <InputLabel for="cycle_limit" :value="`Limit for this cycle only (usually ${bucket.cap ?? '-'})`" />
                            <TextInput id="cycle_limit" v-model="limit" type="number" step="0.01" min="0.01" class="mt-1 block w-48" placeholder="Same as usual" />
                        </div>
                        <PrimaryButton type="submit">Save</PrimaryButton>
                        <SecondaryButton v-if="current.capOverride" type="button" @click="limit = ''; saveLimit('')">Use the usual limit</SecondaryButton>
                    </form>
                </section>

                <section v-if="timeline.length > 1">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">How it filled and emptied</h3>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <apexchart :key="isDark" type="line" height="320" :options="options" :series="series" />
                    </div>
                    <p class="mt-2 text-xs text-gray-400">The last group is the cycle still running: its figures are what has happened so far plus the plan.</p>
                </section>

                <section>
                    <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Finished cycles</h3>
                    <p v-if="!cycles.length" class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-400 dark:border-gray-700">
                        No cycle has ended yet. Each one is saved here when it closes.
                    </p>
                    <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3 text-left">Cycle</th>
                                    <th class="px-4 py-3 text-right">Started with</th>
                                    <th class="px-4 py-3 text-right">Filled</th>
                                    <th class="px-4 py-3 text-right">Moved in / out</th>
                                    <th class="px-4 py-3 text-right">Spent</th>
                                    <th class="px-4 py-3 text-right">Overflow out</th>
                                    <th v-if="bucket.cycleGoal" class="px-4 py-3 text-right">Goal</th>
                                    <th class="px-4 py-3 text-right">Left</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="cycle in cycles" :key="cycle.start">
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">{{ range(cycle) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums"><Money :amount="cycle.opening" :currency="bucket.currency" /></td>
                                    <td class="px-4 py-3 text-right tabular-nums text-green-600 dark:text-green-400"><Money :amount="cycle.filled" :currency="bucket.currency" /></td>
                                    <td class="px-4 py-3 text-right tabular-nums"><Money :amount="cycle.movedIn" :currency="bucket.currency" /> / <Money :amount="cycle.movedOut" :currency="bucket.currency" /></td>
                                    <td class="px-4 py-3 text-right tabular-nums text-red-600 dark:text-red-400"><Money :amount="cycle.spent" :currency="bucket.currency" /></td>
                                    <td class="px-4 py-3 text-right tabular-nums"><Money :amount="cycle.released" :currency="bucket.currency" /></td>
                                    <td v-if="bucket.cycleGoal" class="px-4 py-3 text-right tabular-nums" :class="cycle.cap && cycle.filled + cycle.movedIn >= cycle.cap ? 'text-green-600 dark:text-green-400' : 'text-gray-500'">
                                        <template v-if="cycle.cap"><Money :amount="cycle.cap" :currency="bucket.currency" /></template><template v-else>-</template>
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium tabular-nums"><Money :amount="cycle.closing" :currency="bucket.currency" /></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
