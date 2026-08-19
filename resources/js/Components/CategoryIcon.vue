<script setup>
import { computed } from 'vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';

const props = defineProps({
    category: {
        type: Object,
        required: true,
    },
    size: {
        type: String,
        default: 'h-8 w-8',
    },
});

const color = computed(() => props.category.color ?? '#6b7280');
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center rounded-full"
        :class="size"
        :style="{ backgroundColor: `${color}1a`, color }"
    >
        <DynamicIcon :name="category.icon ?? 'tag'" class="h-4 w-4" />
    </span>
</template>
