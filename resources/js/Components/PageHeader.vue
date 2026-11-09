<script setup>
import { Link } from '@inertiajs/vue3';
import DynamicIcon from '@/Components/DynamicIcon.vue';

/**
 * The title row of a page, placed in AuthenticatedLayout's #header slot: an optional back link above the title, an
 * optional subtitle, actions on the right (they wrap below the title on small screens) and a #meta row underneath.
 */
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    /** { href, label }: where "back" goes and what it says (e.g. the bucket list). */
    back: { type: Object, default: null },
});
</script>

<template>
    <div class="space-y-2">
        <Link
            v-if="back"
            :href="back.href"
            class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400"
        >
            <DynamicIcon name="chevron-right" class="h-4 w-4 rotate-180" />
            {{ back.label }}
        </Link>

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="break-words text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ subtitle }}</p>
            </div>

            <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2" :aria-label="$t('ui.common.actions')" role="group">
                <slot name="actions" />
            </div>
        </div>

        <div v-if="$slots.meta" class="text-sm text-gray-500 dark:text-gray-400">
            <slot name="meta" />
        </div>
    </div>
</template>
