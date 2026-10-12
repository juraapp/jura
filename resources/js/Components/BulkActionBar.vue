<script setup>
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({
    count: { type: Number, required: true },
    // When set, offers to extend the selection to every row the filters match.
    matchingTotal: { type: Number, default: null },
    allMatching: { type: Boolean, default: false },
});

defineEmits(['clear', 'select-all-matching']);
</script>

<template>
    <div
        v-if="count > 0"
        class="sticky top-2 z-20 mb-3 flex flex-wrap items-center gap-3 rounded-xl border border-primary-200 bg-primary-50 px-4 py-2.5 text-sm shadow-sm dark:border-primary-500/30 dark:bg-primary-500/10"
    >
        <span class="font-medium text-primary-900 dark:text-primary-200">
            {{ $t('ui.shell.bulk.selected', { count: allMatching && matchingTotal ? matchingTotal : count }) }}
        </span>

        <button
            v-if="matchingTotal && !allMatching && matchingTotal > count"
            type="button"
            class="text-primary-700 underline hover:no-underline dark:text-primary-300"
            @click="$emit('select-all-matching')"
        >
            {{ $t('ui.shell.bulk.select_all_matching', { count: matchingTotal }) }}
        </button>

        <div class="ml-auto flex flex-wrap items-center gap-2">
            <slot />
            <SecondaryButton type="button" @click="$emit('clear')">{{ $t('ui.common.clear') }}</SecondaryButton>
        </div>
    </div>
</template>
