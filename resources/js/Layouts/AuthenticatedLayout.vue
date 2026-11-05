<script setup>
import Sidebar from '@/Components/Sidebar.vue';
import Topbar from '@/Components/Topbar.vue';
import ToastContainer from '@/Components/ToastContainer.vue';
import GlobalTransactionModal from '@/Components/GlobalTransactionModal.vue';
import { computed } from 'vue';

/**
 * `width` makes the layout own the page width: the header and the page body share one container, wide (max-w-7xl)
 * for lists, tables and dashboards, narrow (max-w-3xl) for forms and settings. Without it the page sizes its own body
 * (the older pattern) and only the header is contained.
 */
const props = defineProps({
    width: {
        type: String,
        default: null,
        validator: (value) => value === null || ['wide', 'narrow'].includes(value),
    },
});

const container = computed(() => ['mx-auto px-4 sm:px-6 lg:px-8', props.width === 'narrow' ? 'max-w-3xl' : 'max-w-7xl']);
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-gray-50 dark:bg-gray-900">
        <Sidebar />

        <div class="flex min-w-0 flex-1 flex-col">
            <Topbar />

            <main class="flex-1 overflow-y-auto">
                <header v-if="$slots.header" class="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <div :class="[container, 'py-6']">
                        <slot name="header" />
                    </div>
                </header>

                <div v-if="width" :class="[container, 'space-y-6 py-8']">
                    <slot />
                </div>
                <slot v-else />
            </main>
        </div>
    </div>

    <ToastContainer />
    <GlobalTransactionModal />
</template>
