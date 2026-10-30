<script setup>
/**
 * Brand primary button. Renders a native `<button>` (default `type="submit"`, the HTML default the auth
 * and profile forms rely on) or, with `as`, an `<a>`/Inertia `Link` that looks identical.
 */
defineProps({
    as: {
        type: [String, Object],
        default: 'button',
    },
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <component
        :is="as"
        :type="as === 'button' ? type : undefined"
        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-transparent bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition duration-150 ease-in-out hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300 disabled:cursor-not-allowed disabled:bg-primary-400 disabled:shadow-none dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800 dark:disabled:bg-primary-900 dark:disabled:text-primary-300"
    >
        <slot />
    </component>
</template>
