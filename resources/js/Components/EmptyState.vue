<script setup>
import { Link } from '@inertiajs/vue3';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

/**
 * The zero-rows state of a list. With `actionLabel` it shows its main action as a primary button: a link to
 * `actionHref`, or (without one) a button that emits `action`. The #action slot still works for anything else.
 */
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
    actionLabel: {
        type: String,
        default: null,
    },
    actionIcon: {
        type: String,
        default: null,
    },
    actionHref: {
        type: String,
        default: null,
    },
});

defineEmits(['action']);
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

        <div v-if="actionLabel || $slots.action" class="mt-5">
            <slot name="action">
                <PrimaryButton v-if="actionHref" :as="Link" :href="actionHref">
                    <DynamicIcon v-if="actionIcon" :name="actionIcon" class="h-4 w-4" />
                    {{ actionLabel }}
                </PrimaryButton>
                <PrimaryButton v-else type="button" @click="$emit('action')">
                    <DynamicIcon v-if="actionIcon" :name="actionIcon" class="h-4 w-4" />
                    {{ actionLabel }}
                </PrimaryButton>
            </slot>
        </div>
    </div>
</template>
