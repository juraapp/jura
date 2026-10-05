<script setup>
import Sidebar from '@/Components/Sidebar.vue';
import Topbar from '@/Components/Topbar.vue';
import ToastContainer from '@/Components/ToastContainer.vue';
import GlobalTransactionModal from '@/Components/GlobalTransactionModal.vue';
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-gray-50 dark:bg-gray-900">
        <Sidebar />

        <div class="flex min-w-0 flex-1 flex-col">
            <Topbar />

            <main class="flex-1 overflow-y-auto">
                <header v-if="$slots.header" class="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        <slot name="header" />
                    </div>
                </header>

                <slot />
            </main>
        </div>
    </div>

    <ToastContainer />
    <GlobalTransactionModal />
</template>
