<script setup>
/**
 * Brand destructive button. Defaults to `type="submit"` like `PrimaryButton`.
 */
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition duration-150 ease-in-out hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-300 disabled:cursor-not-allowed disabled:bg-red-400 disabled:shadow-none dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-900 dark:disabled:bg-red-900 dark:disabled:text-red-300"
    >
        <slot />
    </button>
</template>
