<script setup>
import { computed } from 'vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import Money from '@/Components/Money.vue';
import { useFormat } from '@/composables/useFormat';

const format = useFormat();

const props = defineProps({
    result: { type: Object, required: true },
    currency: { type: String, required: true },
    horizonMonths: { type: Number, required: true },
});

const formatDate = (date) => format.date(date);

const waiting = (days) => {
    if (days === 0) return 'Today';
    if (days < 45) return `in ${days} day${days === 1 ? '' : 's'}`;
    const months = Math.round(days / 30.4);

    return `in ~${months} month${months === 1 ? '' : 's'}`;
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
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400"><Money :amount="line.price" :currency="currency" /></p>
                    </div>
                    <div class="shrink-0 text-right">
                        <template v-if="line.purchaseDate">
                            <p class="text-sm font-semibold text-green-600 dark:text-green-400">{{ formatDate(line.purchaseDate) }}</p>
                            <p class="text-xs text-gray-400">{{ waiting(line.delayDays) }}</p>
                        </template>
                        <template v-else-if="line.blocked === 'accounts'">
                            <p class="text-sm font-semibold text-red-600 dark:text-red-400">Blocked</p>
                            <p class="text-xs text-gray-400">No account fits the item and its bucket</p>
                        </template>
                        <template v-else>
                            <p class="text-sm font-semibold text-amber-600 dark:text-amber-400">Not in {{ horizonMonths }} months</p>
                            <p class="text-xs text-gray-400">needs <Money :amount="line.shortfall" :currency="currency" /> more</p>
                        </template>
                    </div>
                </div>
                <p v-if="line.late" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">
                    <DynamicIcon name="exclamation-triangle" class="mr-1 inline h-3.5 w-3.5" />Later than you wanted it
                </p>
            </li>
        </ol>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            <template v-if="boughtCount === result.lines.length">Everything bought by {{ formatDate(lastDate) }}.</template>
            <template v-else>{{ boughtCount }} of {{ result.lines.length }} can be bought within {{ horizonMonths }} months.</template>
        </p>

        <details v-if="result.buckets.length" class="mt-2 text-xs">
            <summary class="cursor-pointer font-medium text-gray-500 dark:text-gray-400">Money left in your buckets at the end</summary>
            <ul class="mt-1 space-y-0.5">
                <li v-for="bucket in result.buckets" :key="bucket.id" class="flex justify-between text-gray-500 dark:text-gray-400">
                    <span>{{ bucket.name }}</span>
                    <span class="tabular-nums"><Money :amount="bucket.endBalance" :currency="currency" /></span>
                </li>
            </ul>
        </details>
    </div>
</template>
