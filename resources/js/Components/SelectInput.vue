<script setup>
defineProps({
    modelValue: {
        type: [String, Number, null],
        default: '',
    },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <select
        :value="modelValue ?? ''"
        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
        @change="$emit('update:modelValue', $event.target.value)"
    >
        <slot />
    </select>
</template>
