<script setup>
import { Deferred, usePage } from '@inertiajs/vue3';
import Money from '@/Components/Money.vue';

const page = usePage();
</script>

<template>
    <Deferred data="moneyMap" group="topbar">
        <template #fallback>
            <div class="hidden flex-col leading-tight md:flex">
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.money_map.ready_to_assign') }}</span>
                <span class="h-5 w-20 animate-pulse rounded bg-gray-200 dark:bg-gray-700"></span>
            </div>
        </template>

        <div v-if="page.props.moneyMap?.length" class="hidden flex-col leading-tight md:flex">
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.money_map.ready_to_assign') }}</span>
            <span
                v-for="row in page.props.moneyMap"
                :key="row.currency"
                class="text-sm font-semibold"
                :class="row.overAssigned ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'"
            >
                <Money :amount="row.readyToAssign" :currency="row.currency" />
            </span>
        </div>
    </Deferred>
</template>
