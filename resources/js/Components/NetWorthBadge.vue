<script setup>
import { Deferred, usePage } from '@inertiajs/vue3';
import Money from '@/Components/Money.vue';

const page = usePage();
</script>

<template>
    <Deferred data="netWorth" group="topbar">
        <template #fallback>
            <div class="hidden flex-col leading-tight sm:flex">
                <span class="text-xs text-gray-500 dark:text-gray-400">Net worth</span>
                <span class="h-5 w-20 animate-pulse rounded bg-gray-200 dark:bg-gray-700"></span>
            </div>
        </template>

        <div class="hidden flex-col leading-tight sm:flex">
            <span class="text-xs text-gray-500 dark:text-gray-400">Net worth</span>
            <span
                v-for="(amount, currency) in page.props.netWorth"
                :key="currency"
                class="text-sm font-semibold text-gray-900 dark:text-white"
            >
                <Money :amount="amount" :currency="currency" />
            </span>
        </div>
    </Deferred>
</template>
