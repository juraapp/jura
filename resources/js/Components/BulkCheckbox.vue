<script setup>
// A row checkbox for a useBulkSelection() state. With `page`, it is the "select all on this page" box.
const props = defineProps({
    bulk: { type: Object, required: true },
    id: { type: [Number, String], default: null },
    page: { type: Boolean, default: false },
    label: { type: String, default: 'Select' },
});
</script>

<template>
    <input
        type="checkbox"
        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
        :aria-label="page ? 'Select all' : label"
        :checked="page ? bulk.allOnPageSelected.value : bulk.isSelected(id)"
        @change="page ? bulk.togglePage() : bulk.toggle(id)"
    />
</template>
