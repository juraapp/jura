<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import CategoryIcon from '@/Components/CategoryIcon.vue';
import DangerButton from '@/Components/DangerButton.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import Modal from '@/Components/Modal.vue';
import Money from '@/Components/Money.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useFormat } from '@/composables/useFormat';
import { useTransactionModal } from '@/composables/useTransactionModal';
import { pause, resume, destroy } from '@/actions/App/Modules/Planning/Http/Controllers/PlannedSeriesController';

/**
 * Repeating plans as rules: next date, frequency, end, and whether they record themselves or are paused.
 */
defineProps({
    series: { type: Array, required: true },
});

const format = useFormat();
const { openSeries } = useTransactionModal();

const pauseSeries = (rule) => router.post(pause.url(rule.id), {}, { preserveScroll: true });
const resumeSeries = (rule) => router.post(resume.url(rule.id), {}, { preserveScroll: true });

const confirmingDelete = ref(null);
const deleteSeries = () => {
    router.delete(destroy.url(confirmingDelete.value.id), {
        preserveScroll: true,
        onFinish: () => (confirmingDelete.value = null),
    });
};
</script>

<template>
    <div>
        <p v-if="series.length === 0" class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
            {{ $t('ui.cash_flow.repeating_empty') }}
        </p>

        <ul v-else class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
            <li v-for="rule in series" :key="rule.id" class="flex flex-wrap items-center gap-3 px-4 py-3" :class="{ 'opacity-60': !rule.isActive }">
                <CategoryIcon v-if="rule.category" :category="rule.category" size="h-8 w-8" />
                <div v-else class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-700">
                    <DynamicIcon name="arrow-path" class="h-4 w-4" />
                </div>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ rule.description }}</p>
                    <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ rule.frequencyLabel }}</span>
                        <span v-if="rule.isActive && rule.nextDate">· {{ $t('ui.cash_flow.next') }} {{ format.date(rule.nextDate) }}</span>
                        <span v-if="rule.endsOn">· {{ $t('ui.cash_flow.ends') }} {{ format.date(rule.endsOn) }}</span>
                        <span v-if="rule.account">· {{ rule.account.name }}</span>
                        <span v-if="rule.autoPost && rule.isActive" class="rounded-full bg-primary-50 px-2 py-0.5 font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">{{ $t('ui.cash_flow.auto') }}</span>
                        <span v-if="!rule.isActive" class="rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $t('ui.cash_flow.paused') }}</span>
                    </p>
                </div>

                <p class="text-right text-sm font-semibold tabular-nums" :class="rule.type === 'income' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                    {{ rule.type === 'income' ? '+' : '-' }}<Money :amount="rule.amount" :currency="rule.currency" />
                </p>

                <div class="flex items-center gap-1">
                    <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" :title="$t('ui.cash_flow.edit')" :aria-label="$t('ui.cash_flow.edit')" @click="openSeries(rule.id)">
                        <DynamicIcon name="pencil" class="h-4 w-4" />
                    </button>
                    <button v-if="rule.isActive" type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" :title="$t('ui.cash_flow.pause')" :aria-label="$t('ui.cash_flow.pause')" @click="pauseSeries(rule)">
                        <DynamicIcon name="pause" class="h-4 w-4" />
                    </button>
                    <button v-else type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" :title="$t('ui.cash_flow.resume')" :aria-label="$t('ui.cash_flow.resume')" @click="resumeSeries(rule)">
                        <DynamicIcon name="play" class="h-4 w-4" />
                    </button>
                    <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" :title="$t('ui.cash_flow.delete_series')" :aria-label="$t('ui.cash_flow.delete_series')" @click="confirmingDelete = rule">
                        <DynamicIcon name="trash" class="h-4 w-4" />
                    </button>
                </div>
            </li>
        </ul>

        <Modal :show="confirmingDelete !== null" @close="confirmingDelete = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $t('ui.cash_flow.delete_series_title', { name: confirmingDelete?.description ?? '' }) }}</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $t('ui.cash_flow.delete_series_hint') }}</p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="confirmingDelete = null">{{ $t('ui.common.cancel') }}</SecondaryButton>
                    <DangerButton type="button" @click="deleteSeries">{{ $t('ui.common.delete') }}</DangerButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
