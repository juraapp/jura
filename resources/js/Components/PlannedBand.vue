<script setup>
import { Link } from '@inertiajs/vue3';
import CategoryIcon from '@/Components/CategoryIcon.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import Money from '@/Components/Money.vue';
import { useFormat } from '@/composables/useFormat';
import { usePlanActions } from '@/composables/usePlanActions';

/**
 * Open plans shown with real transactions: overdue ones (red, with a suggested match) or upcoming ones
 * (dashed, projected, not in the balance).
 */
defineProps({
    /** { count, items } from UpcomingPlans::listBands */
    band: { type: Object, required: true },
    variant: { type: String, default: 'upcoming' },
    title: { type: String, required: true },
    seeAllUrl: { type: String, required: true },
});

const format = useFormat();
const actions = usePlanActions();
</script>

<template>
    <section
        v-if="band.count > 0"
        class="rounded-xl border p-3"
        :class="variant === 'overdue'
            ? 'border-red-200 bg-red-50/60 dark:border-red-500/30 dark:bg-red-500/5'
            : 'border-dashed border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-800'"
    >
        <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2 px-1">
            <h3 class="text-sm font-semibold" :class="variant === 'overdue' ? 'text-red-700 dark:text-red-300' : 'text-gray-700 dark:text-gray-200'">
                {{ title }} <span class="font-normal text-gray-500 dark:text-gray-400">({{ band.count }})</span>
            </h3>
            <p v-if="variant !== 'overdue'" class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.transactions.not_in_balance') }}</p>
        </div>

        <ul class="divide-y divide-gray-100 dark:divide-gray-700">
            <li v-for="event in band.items" :key="event.id" class="flex flex-wrap items-center gap-3 px-1 py-2">
                <span class="w-20 shrink-0 text-sm text-gray-500 dark:text-gray-400">{{ format.date(event.date, { year: false }) }}</span>
                <CategoryIcon v-if="event.category" :category="event.category" size="h-7 w-7" />
                <span v-else class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-700">
                    <DynamicIcon name="calendar" class="h-3.5 w-3.5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                        {{ event.description }}
                        <span v-if="event.seriesId" class="ml-1 text-xs font-normal text-gray-400"><DynamicIcon name="arrow-path" class="inline h-3 w-3" /></span>
                        <span v-if="event.autoPost" class="ml-1 rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">{{ $t('ui.cash_flow.auto') }}</span>
                    </p>
                    <p v-if="event.postError" class="text-xs text-red-600 dark:text-red-400">{{ $t(`ui.cash_flow.post_error.${event.postError}`) }}</p>
                    <p v-else-if="event.suggestedMatch" class="text-xs text-primary-700 dark:text-primary-300">
                        {{ $t('ui.cash_flow.looks_done', { description: event.suggestedMatch.description || $t('ui.cash_flow.a_transaction') }) }}
                    </p>
                    <p v-else-if="event.account" class="text-xs text-gray-400">{{ event.account.name }}</p>
                </div>
                <p class="text-sm font-semibold tabular-nums" :class="event.type === 'income' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                    {{ event.type === 'income' ? '+' : '−' }}<Money :amount="event.amount" :currency="event.currency" />
                </p>
                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                    <button v-if="event.suggestedMatch" type="button" class="text-primary-600 hover:underline dark:text-primary-400" @click="actions.match(event)">{{ $t('ui.cash_flow.match') }}</button>
                    <button type="button" class="text-green-700 hover:underline dark:text-green-400" @click="actions.confirm(event)">{{ $t('ui.cash_flow.confirm') }}</button>
                    <button type="button" class="text-gray-600 hover:underline dark:text-gray-300" @click="actions.adjust(event)">{{ $t('ui.cash_flow.adjust') }}</button>
                    <button type="button" class="text-gray-600 hover:underline dark:text-gray-300" @click="actions.skip(event)">{{ $t('ui.cash_flow.skip') }}</button>
                    <button type="button" class="text-gray-600 hover:underline dark:text-gray-300" @click="actions.edit(event)">{{ $t('ui.cash_flow.edit') }}</button>
                </div>
            </li>
        </ul>

        <Link v-if="band.count > band.items.length" :href="seeAllUrl" class="mt-1 inline-block px-1 text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">
            {{ $t('ui.transactions.see_all', { count: band.count }) }}
        </Link>
    </section>
</template>
