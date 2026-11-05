<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import Money from '@/Components/Money.vue';
import { useFormat } from '@/composables/useFormat';

const format = useFormat();
const { t } = useI18n();

const props = defineProps({
    result: { type: Object, required: true },
    currency: { type: String, required: true },
    horizonMonths: { type: Number, required: true },
});

const formatDate = (date) => format.date(date);

const waiting = (days) => {
    if (days === 0) return t('ui.scenario.today');
    if (days < 45) return t('ui.scenario.in_days', { count: days }, days);
    const months = Math.round(days / 30.4);

    return t('ui.scenario.in_months', { count: months }, months);
};

const boughtCount = computed(() => props.result.lines.filter((line) => line.purchaseDate).length);
const lastDate = computed(() => {
    const dates = props.result.lines.map((line) => line.purchaseDate).filter(Boolean).sort();

    return dates.length ? dates[dates.length - 1] : null;
});
</script>

<template>
    <div>
        <ol class="space-y-2">
            <li
                v-for="(line, index) in result.lines"
                :key="line.itemId"
                class="rounded-lg border px-3 py-2"
                :class="line.purchaseDate ? 'border-gray-200 dark:border-gray-700' : 'border-dashed border-gray-300 dark:border-gray-600'"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                            <span class="mr-1.5 text-xs font-semibold text-gray-400">{{ index + 1 }}.</span>{{ line.name }}
                            <span v-if="line.parked" class="ml-1.5 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $t('ui.scenario.parked_badge') }}</span>
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400"><Money :amount="line.price" :currency="currency" /></p>
                        <p v-if="line.purchaseDate && line.fromBucket > 0" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ $t('ui.scenario.from_bucket', { amount: format.money(line.fromBucket, currency) }) }}<template v-if="line.fromFree > 0"> &middot; {{ $t('ui.scenario.from_ready', { amount: format.money(line.fromFree, currency) }) }}</template>
                        </p>
                        <p v-else-if="line.purchaseDate" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.scenario.from_ready', { amount: format.money(line.fromFree, currency) }) }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <template v-if="line.purchaseDate">
                            <p class="text-sm font-semibold text-green-600 dark:text-green-400">{{ formatDate(line.purchaseDate) }}</p>
                            <p class="text-xs text-gray-400">{{ waiting(line.delayDays) }}</p>
                        </template>
                        <template v-else-if="line.blocked">
                            <p class="text-sm font-semibold text-red-600 dark:text-red-400">{{ $t('ui.scenario.blocked') }}</p>
                            <p class="text-xs text-gray-400">{{ line.blocked === 'off_budget' ? $t('ui.scenario.blocked_off_budget') : $t('ui.scenario.blocked_hint') }}</p>
                        </template>
                        <template v-else>
                            <p class="text-sm font-semibold text-amber-600 dark:text-amber-400">{{ $t('ui.scenario.not_in', { count: horizonMonths }) }}</p>
                            <p v-if="line.shortfall > 0" class="text-xs text-gray-400">{{ $t('ui.scenario.needs') }} <Money :amount="line.shortfall" :currency="currency" /> {{ $t('ui.scenario.more') }}</p>
                            <p v-if="line.limitedBy" class="text-xs text-gray-400">{{ $t(`ui.scenario.limited_${line.limitedBy}`) }}</p>
                        </template>
                    </div>
                </div>
                <p v-if="line.late" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">
                    <DynamicIcon name="exclamation-triangle" class="mr-1 inline h-3.5 w-3.5" />{{ $t('ui.scenario.late') }}
                </p>
                <p v-if="line.parked" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.scenario.parked') }}</p>
                <p v-if="line.freeDipsOn" class="mt-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                    <DynamicIcon name="exclamation-triangle" class="mr-1 inline h-3.5 w-3.5" />{{ $t('ui.scenario.dips_warning', { date: formatDate(line.freeDipsOn) }) }}
                </p>
                <p v-if="(line.warnings ?? []).includes('cap_below_price')" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    <DynamicIcon name="information-circle" class="mr-1 inline h-3.5 w-3.5" />{{ $t('ui.scenario.cap_warning') }}
                </p>
            </li>
        </ol>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            <template v-if="boughtCount === result.lines.length">{{ $t('ui.scenario.all_bought', { date: formatDate(lastDate) }) }}</template>
            <template v-else>{{ $t('ui.scenario.some_bought', { bought: boughtCount, total: result.lines.length, months: horizonMonths }) }}</template>
        </p>

        <dl v-if="result.readyToAssign" class="mt-2 grid grid-cols-1 gap-1 text-xs text-gray-500 sm:grid-cols-2 dark:text-gray-400">
            <div class="flex justify-between gap-2 sm:block">
                <dt>{{ $t('ui.planner.ready_now') }}</dt>
                <dd class="font-medium tabular-nums text-gray-700 dark:text-gray-200"><Money :amount="result.readyToAssign.start" :currency="currency" signed /></dd>
            </div>
            <div class="flex justify-between gap-2 sm:block">
                <dt>{{ $t('ui.planner.ready_end') }}</dt>
                <dd class="font-medium tabular-nums text-gray-700 dark:text-gray-200"><Money :amount="result.readyToAssign.end" :currency="currency" signed /></dd>
            </div>
        </dl>

        <details v-if="result.buckets.length" class="mt-2 text-xs">
            <summary class="cursor-pointer font-medium text-gray-500 dark:text-gray-400">{{ $t('ui.scenario.left') }}</summary>
            <ul class="mt-1 space-y-0.5">
                <li v-for="bucket in result.buckets" :key="bucket.id" class="flex justify-between text-gray-500 dark:text-gray-400">
                    <span>{{ bucket.name }}</span>
                    <span class="tabular-nums"><Money :amount="bucket.endBalance" :currency="currency" /></span>
                </li>
            </ul>
        </details>
    </div>
</template>
