<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BulkActionBar from '@/Components/BulkActionBar.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

// The standard "delete the selected rows" bar for a useBulkSelection() state.
const props = defineProps({
    bulk: { type: Object, required: true },
    url: { type: String, required: true },
    noun: { type: String, required: true },
    note: { type: String, default: 'This action cannot be undone.' },
});

const confirming = ref(false);

const run = () => {
    router.post(props.url, { ids: props.bulk.ids.value }, {
        preserveScroll: true,
        onSuccess: () => props.bulk.clear(),
        onFinish: () => (confirming.value = false),
    });
};
</script>

<template>
    <BulkActionBar :count="bulk.count.value" @clear="bulk.clear()">
        <DangerButton type="button" @click="confirming = true">Delete</DangerButton>
    </BulkActionBar>

    <Modal :show="confirming" @close="confirming = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delete {{ bulk.count.value }} {{ noun }}?</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ note }}</p>
            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton type="button" @click="confirming = false">Cancel</SecondaryButton>
                <DangerButton type="button" @click="run">Delete</DangerButton>
            </div>
        </div>
    </Modal>
</template>
