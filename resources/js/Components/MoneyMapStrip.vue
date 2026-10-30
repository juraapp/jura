<script setup>
import { Deferred, usePage } from '@inertiajs/vue3';
import Money from '@/Components/Money.vue';
import { useFormat } from '@/composables/useFormat';

const page = usePage();
const format = useFormat();
</script>

<template>
    <Deferred data="moneyMap" group="topbar">
        <template #fallback>
            <div class="h-20 animate-pulse rounded-xl border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800"></div>
        </template>

        <section
            v-if="page.props.moneyMap?.length"
            class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            :aria-label="$t('ui.money_map.title')"
        >
            <div
                v-for="row in page.props.moneyMap"
                :key="row.currency"
                class="grid grid-cols-1 gap-3 border-gray-100 py-2 first:pt-0 last:pb-0 sm:grid-cols-3 dark:border-gray-700 [&:not(:first-child)]:border-t"
            >
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.money_map.in_accounts') }}</p>
                    <p class="font-semibold text-gray-900 dark:text-white"><Money :amount="row.inAccounts" :currency="row.currency" /></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.money_map.in_buckets') }}</p>
                    <p class="font-semibold text-gray-900 dark:text-white"><Money :amount="row.inBuckets" :currency="row.currency" /></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.money_map.ready_to_assign') }}</p>
                    <p
                        class="font-semibold"
                        :class="row.overAssigned ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'"
                    >
                        <Money :amount="row.readyToAssign" :currency="row.currency" />
                    </p>
                    <p v-if="row.overAssigned" class="text-xs text-red-600 dark:text-red-400">
                        {{ $t('ui.money_map.over_assigned', { amount: format.money(Math.abs(row.readyToAssign), row.currency) }) }}
                    </p>
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.money_map.hint') }} {{ $t('ui.money_map.off_budget_note') }}</p>
        </section>
    </Deferred>
</template>
