<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    amount: {
        type: [Number, String],
        required: true,
    },
    currency: {
        type: String,
        default: null,
    },
    signed: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();

const value = computed(() => Number(props.amount));
const currencyCode = computed(() => props.currency ?? page.props.auth.user?.currency_default ?? 'COP');
const locale = computed(() => page.props.auth.user?.locale ?? 'en');

const formatted = computed(() => {
    const prefix = props.signed && value.value > 0 ? '+' : '';

    return prefix + new Intl.NumberFormat(locale.value, {
        style: 'currency',
        currency: currencyCode.value,
    }).format(value.value);
});
</script>

<template>
    <span class="tabular-nums">{{ formatted }}</span>
</template>
