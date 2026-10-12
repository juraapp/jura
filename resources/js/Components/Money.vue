<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useFormat } from '@/composables/useFormat';

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
const format = useFormat();

const formatted = computed(() => format.money(value.value, currencyCode.value, { signed: props.signed }));
</script>

<template>
    <span class="tabular-nums">{{ formatted }}</span>
</template>
