<script setup>
import { watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import { useToast } from '@/composables/useToast';

const { toasts, push, remove } = useToast();
const page = usePage();

// The server flashes success/error/warning messages (and an optional Undo) with every redirect.
watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) return;

        ['success', 'error', 'warning'].forEach((type) => {
            if (!flash[type]) return;

            const undo = type === 'success' ? flash.undo : null;
            push({
                type,
                message: flash[type],
                action: undo ? { label: 'Undo', onClick: () => router.post(undo.url, { ids: undo.ids }, { preserveScroll: true }) } : null,
            });
        });
    },
    { immediate: true },
);
</script>

<template>
    <div class="fixed bottom-4 right-4 z-50 flex w-full max-w-xs flex-col gap-3">
        <TransitionGroup
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-lg dark:border-gray-700 dark:bg-gray-800"
            >
                <span
                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                    :class="{
                        'bg-green-100 text-green-500 dark:bg-green-800 dark:text-green-200': toast.type === 'success',
                        'bg-red-100 text-red-500 dark:bg-red-800 dark:text-red-200': toast.type === 'error',
                        'bg-orange-100 text-orange-500 dark:bg-orange-800 dark:text-orange-200': toast.type === 'warning',
                    }"
                >
                    <DynamicIcon v-if="toast.type === 'success'" name="check-circle" class="h-4 w-4" />
                    <DynamicIcon v-else name="exclamation-triangle" class="h-4 w-4" />
                </span>

                <p class="text-sm font-normal text-gray-700 dark:text-gray-300">{{ toast.message }}</p>

                <button
                    v-if="toast.action"
                    type="button"
                    class="shrink-0 text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400"
                    @click="toast.action.onClick(); remove(toast.id)"
                >
                    {{ toast.action.label }}
                </button>

                <button
                    type="button"
                    class="ms-auto shrink-0 rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                    @click="remove(toast.id)"
                >
                    <DynamicIcon name="x-mark" class="h-4 w-4" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
