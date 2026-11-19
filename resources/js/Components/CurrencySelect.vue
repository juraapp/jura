<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SelectInput from '@/Components/SelectInput.vue';
import { currencyCodes, currencyLabel } from '@/currencies';

/**
 * A currency picker: the user's default currency first, then the currencies their accounts use (`accountCurrencies`),
 * then the rest of the common ISO list. `restrictTo` limits it (for example to the currencies a bucket can use).
 */
const props = defineProps({
    modelValue: { type: [String, null], default: '' },
    accountCurrencies: { type: Array, default: () => [] },
    restrictTo: { type: Array, default: null },
});

defineEmits(['update:modelValue']);

const page = usePage();

const options = computed(() => {
    const user = page.props.auth?.user;

    return currencyCodes({
        defaultCode: user?.currency_default,
        extra: props.accountCurrencies,
        restrictTo: props.restrictTo,
        current: props.modelValue,
    }).map((code) => ({ value: code, label: currencyLabel(code, user?.locale ?? 'en') }));
});
</script>

<template>
    <SelectInput :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)">
        <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
    </SelectInput>
</template>
