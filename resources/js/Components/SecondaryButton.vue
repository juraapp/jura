<script setup>
/**
 * Brand secondary (outlined) button. Defaults to `type="button"`; with `as` it renders an `<a>`/Inertia `Link`.
 */
defineProps({
    as: {
        type: [String, Object],
        default: 'button',
    },
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <component
        :is="as"
        :type="as === 'button' ? type : undefined"
        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-900 shadow-sm transition duration-150 ease-in-out hover:bg-gray-100 hover:text-primary-700 focus:outline-none focus:ring-4 focus:ring-gray-200 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-white disabled:hover:text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white dark:focus:ring-gray-700 dark:disabled:hover:bg-gray-800 dark:disabled:hover:text-gray-300"
    >
        <slot />
    </component>
</template>
