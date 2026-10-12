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
    note: { type: String, default: null },
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
        <DangerButton type="button" @click="confirming = true">{{ $t('ui.common.delete') }}</DangerButton>
    </BulkActionBar>

    <Modal :show="confirming" @close="confirming = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $t('ui.shell.bulk.delete_title', { count: bulk.count.value, noun }) }}</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ note ?? $t('ui.common.cannot_be_undone') }}</p>
            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton type="button" @click="confirming = false">{{ $t('ui.common.cancel') }}</SecondaryButton>
                <DangerButton type="button" @click="run">{{ $t('ui.common.delete') }}</DangerButton>
            </div>
        </div>
    </Modal>
</template>
