<script setup>
import DynamicIcon from '@/Components/DynamicIcon.vue';

defineProps({
    label: {
        type: String,
        required: true,
    },
    icon: {
        type: String,
        default: null,
    },
    iconColor: {
        type: String,
        default: 'text-primary-600 dark:text-primary-400',
    },
    iconBg: {
        type: String,
        default: 'bg-primary-50 dark:bg-primary-500/10',
    },
});
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-start justify-between">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ label }}</p>

            <span
                v-if="icon"
                class="flex h-9 w-9 items-center justify-center rounded-lg"
                :class="[iconBg, iconColor]"
            >
                <DynamicIcon :name="icon" class="h-5 w-5" />
            </span>
        </div>

        <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
            <slot />
        </div>

        <div v-if="$slots.footer" class="mt-2 text-sm">
            <slot name="footer" />
        </div>
    </div>
</template>
