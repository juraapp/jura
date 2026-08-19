<script setup>
import DynamicIcon from '@/Components/DynamicIcon.vue';
import { useToast } from '@/composables/useToast';

const { toasts, remove } = useToast();
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
