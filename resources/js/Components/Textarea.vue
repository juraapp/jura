<script setup>
import { ref } from 'vue';

/**
 * A multi-line text field styled like the other inputs.
 */
const model = defineModel({ type: [String, null], default: '' });

const input = ref(null);
defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <textarea
        ref="input"
        v-model="model"
        rows="3"
        class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-400"
    />
</template>
