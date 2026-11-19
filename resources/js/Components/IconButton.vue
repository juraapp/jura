<script setup>
import DynamicIcon from '@/Components/DynamicIcon.vue';

/**
 * A button that shows only an icon. `label` is required: it becomes the tooltip and the accessible name. Renders a
 * `<button type="button">` or, with `as`, a link (`as="a"` or Inertia's `Link`) that looks the same.
 */
defineProps({
    label: { type: String, required: true },
    icon: { type: String, required: true },
    /** ghost (grey, the default) | danger (turns red on hover) | primary */
    variant: { type: String, default: 'ghost', validator: (value) => ['ghost', 'danger', 'primary'].includes(value) },
    as: { type: [String, Object], default: 'button' },
    type: { type: String, default: 'button' },
    iconClass: { type: String, default: 'h-4 w-4' },
});

const variants = {
    ghost: 'text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-gray-200',
    danger: 'text-gray-400 hover:bg-red-50 hover:text-red-600 dark:text-gray-500 dark:hover:bg-red-500/10 dark:hover:text-red-400',
    primary: 'text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-500/10',
};
</script>

<template>
    <component
        :is="as"
        :type="as === 'button' ? type : undefined"
        :title="label"
        :aria-label="label"
        class="inline-flex items-center justify-center rounded-lg p-1.5 transition focus:outline-none focus:ring-2 focus:ring-primary-300 disabled:cursor-not-allowed disabled:opacity-30 dark:focus:ring-primary-800"
        :class="variants[variant]"
    >
        <DynamicIcon :name="icon" :class="iconClass" />
    </component>
</template>
