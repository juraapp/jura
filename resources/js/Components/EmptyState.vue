<script setup>
import DynamicIcon from '@/Components/DynamicIcon.vue';

defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: null,
    },
    icon: {
        type: String,
        default: 'inbox',
    },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 px-6 py-16 text-center dark:border-gray-700">
        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500">
            <DynamicIcon :name="icon" class="h-7 w-7" />
        </div>

        <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ title }}</h3>

        <p v-if="description" class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">
            {{ description }}
        </p>

        <div v-if="$slots.action" class="mt-5">
            <slot name="action" />
        </div>
    </div>
</template>
