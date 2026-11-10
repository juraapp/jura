<script setup>
import DynamicIcon from '@/Components/DynamicIcon.vue';

defineProps({
    processing: { type: Boolean, default: false },
});
</script>

<template>
    <button
        type="submit"
        :disabled="processing"
        :aria-busy="processing"
        class="group inline-flex min-h-[52px] w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-3 text-center text-base font-semibold text-white shadow-lg shadow-primary-600/25 transition duration-200 hover:bg-primary-700 hover:shadow-primary-700/30 focus:outline-none focus:ring-4 focus:ring-primary-300 active:scale-[0.99] disabled:cursor-wait disabled:opacity-75 motion-reduce:transition-none motion-reduce:active:scale-100 dark:bg-primary-500 dark:shadow-none dark:hover:bg-primary-400 dark:focus:ring-primary-800"
    >
        <svg v-if="processing" aria-hidden="true" class="h-4 w-4 shrink-0 animate-spin motion-reduce:animate-none" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity="0.25" />
            <path d="M12 3a9 9 0 0 1 9 9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        </svg>
        <span aria-live="polite">{{ processing ? $t('ui.auth.processing') : '' }}<slot v-if="!processing" /></span>
        <DynamicIcon
            v-if="!processing"
            name="arrow-right"
            class="h-4 w-4 shrink-0 transition-transform duration-200 group-hover:translate-x-0.5 motion-reduce:transition-none"
        />
    </button>
</template>
