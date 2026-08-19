<script setup>
import { computed } from 'vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';

const props = defineProps({
    value: {
        type: Number,
        required: true,
    },
    favorableWhenPositive: {
        type: Boolean,
        default: true,
    },
    suffix: {
        type: String,
        default: '%',
    },
});

const isUp = computed(() => props.value > 0);
const isFlat = computed(() => Math.abs(props.value) < 0.05);
const isFavorable = computed(() => (isFlat.value ? null : (props.favorableWhenPositive ? isUp.value : !isUp.value)));

const classes = computed(() => {
    if (isFlat.value) {
        return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300';
    }

    return isFavorable.value
        ? 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400'
        : 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400';
});

const label = computed(() => {
    const sign = isUp.value && !isFlat.value ? '+' : '';

    return `${sign}${Math.abs(props.value).toFixed(1)}${props.suffix}`;
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
        :class="classes"
    >
        <DynamicIcon
            v-if="!isFlat"
            name="arrow-trending-up"
            class="h-3.5 w-3.5"
            :class="{ '-scale-y-100': !isUp }"
        />
        {{ label }}
    </span>
</template>
