<script setup>
import { Link } from '@inertiajs/vue3';
import Money from '@/Components/Money.vue';
import { useFormat } from '@/composables/useFormat';
import { usePlanActions } from '@/composables/usePlanActions';

/**
 * The review inbox: plans whose date has passed and that are still open, or that could not be recorded
 * automatically. Each one is confirmed, matched to a transaction already recorded, adjusted, skipped or edited.
 */
defineProps({
    /** { count, items } from UpcomingPlans::needsReview */
    review: { type: Object, required: true },
    /** A link to the full inbox, shown when the list is cut short (the dashboard card). */
    seeAllUrl: { type: String, default: null },
});

const format = useFormat();
const actions = usePlanActions();
</script>

<template>
    <div>
        <p v-if="review.count === 0" class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
            {{ $t('ui.cash_flow.review_empty') }}
        </p>

        <ul v-else class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-red-200 bg-white shadow-sm dark:divide-gray-700 dark:border-red-500/30 dark:bg-gray-800">
            <li v-for="event in review.items" :key="event.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
                <span class="w-20 shrink-0 text-sm font-medium text-red-600 dark:text-red-400">{{ format.date(event.date, { year: false }) }}</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ event.description }}</p>
                    <p v-if="event.postError" class="text-xs text-red-600 dark:text-red-400">{{ $t(`ui.cash_flow.post_error.${event.postError}`) }}</p>
                    <p v-else-if="event.suggestedMatch" class="text-xs text-primary-700 dark:text-primary-300">
                        {{ $t('ui.cash_flow.looks_done', { description: event.suggestedMatch.description || $t('ui.cash_flow.a_transaction') }) }}
                        (<Money :amount="event.suggestedMatch.amount" :currency="event.currency" />, {{ format.date(event.suggestedMatch.date, { year: false }) }})
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

        <Link v-if="seeAllUrl && review.count > review.items.length" :href="seeAllUrl" class="mt-2 inline-block text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">
            {{ $t('ui.transactions.see_all', { count: review.count }) }}
        </Link>
    </div>
</template>
