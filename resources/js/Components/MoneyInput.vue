<script setup>
import { computed, ref, useId, watch } from 'vue';
import { useFormat } from '@/composables/useFormat';

/**
 * A money field. The user types with their own separators (or the other ones: "1.234,50" and "1,234.50" both work);
 * v-model always gets a plain decimal string ("1234.50"), '' when blank, or what was typed when it cannot be read, so
 * the server's validation message shows. On blur a readable amount is shown again with the user's separators.
 */
const props = defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    currency: { type: String, default: null },
    id: { type: String, default: null },
    placeholder: { type: String, default: null },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'blur']);

const format = useFormat();
const text = ref(format.formatMoney(props.modelValue));
let lastEmitted = props.modelValue;

watch(() => props.modelValue, (value) => {
    if (value !== lastEmitted) {
        text.value = format.formatMoney(value);
        lastEmitted = value;
    }
});

const onInput = (event) => {
    text.value = event.target.value;
    const parsed = format.parseMoney(text.value);
    lastEmitted = parsed === null ? text.value : parsed;
    emit('update:modelValue', lastEmitted);
};

const onBlur = (event) => {
    const parsed = format.parseMoney(text.value);
    if (parsed !== null) {
        text.value = format.formatMoney(parsed);
    }
    emit('blur', event);
};

const input = ref(null);
const fallbackId = useId();
const prefixId = computed(() => `${props.id ?? fallbackId}-currency`);
defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <div class="relative">
        <span
            v-if="currency"
            :id="prefixId"
            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-medium text-gray-500 dark:text-gray-400"
        >{{ currency }}</span>
        <input
            :id="id"
            ref="input"
            type="text"
            inputmode="decimal"
            autocomplete="off"
            :value="text"
            :aria-describedby="currency ? prefixId : undefined"
            :placeholder="placeholder"
            :disabled="disabled"
            class="block w-full rounded-lg border-gray-300 text-sm tabular-nums shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-400"
            :class="currency ? 'pl-12' : ''"
            @input="onInput"
            @blur="onBlur"
        />
    </div>
</template>
