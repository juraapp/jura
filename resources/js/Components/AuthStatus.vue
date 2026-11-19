<script setup>
import DynamicIcon from '@/Components/DynamicIcon.vue';
</script>

<template>
    <div role="status" class="mb-6 flex items-start gap-3 rounded-xl bg-primary-50 p-4 text-sm leading-6 text-primary-800 ring-1 ring-inset ring-primary-200 dark:bg-primary-900/30 dark:text-primary-200 dark:ring-primary-800">
        <DynamicIcon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0" />
        <p><slot /></p>
    </div>
</template>
