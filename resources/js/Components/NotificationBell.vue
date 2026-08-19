<script setup>
import { ref } from 'vue';
import { Deferred, Link, router, usePage } from '@inertiajs/vue3';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import { onClickOutside } from '@/composables/onClickOutside';
import { read, readAll } from '@/routes/notifications';

const page = usePage();
const open = ref(false);
const root = ref(null);

onClickOutside(root, () => (open.value = false));

const markAsRead = (notificationId) => {
    router.patch(read.url(notificationId), {}, { preserveScroll: true, only: ['notifications'] });
};

const markAllAsRead = () => {
    router.patch(readAll.url(), {}, { preserveScroll: true, only: ['notifications'] });
};
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="relative rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
            @click="open = !open"
        >
            <DynamicIcon name="bell" class="h-5 w-5" />
            <span
                v-if="page.props.notifications?.unreadCount > 0"
                class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-semibold text-white"
            >
                {{ page.props.notifications.unreadCount > 9 ? '9+' : page.props.notifications.unreadCount }}
            </span>
            <span class="sr-only">Notifications</span>
        </button>

        <div
            v-show="open"
            class="absolute right-0 z-30 mt-2 w-80 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
        >
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                <p class="shrink-0 text-sm font-semibold text-gray-900 dark:text-white">Notifications</p>
                <button
                    v-if="page.props.notifications?.unreadCount > 0"
                    type="button"
                    class="shrink-0 whitespace-nowrap text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                    @click="markAllAsRead"
                >
                    Mark all
                </button>
            </div>

            <div class="max-h-96 overflow-y-auto">
                <Deferred data="notifications" group="topbar">
                    <template #fallback>
                        <p class="px-4 py-8 text-center text-sm text-gray-400">Loading…</p>
                    </template>

                    <template v-if="page.props.notifications?.items?.length">
                        <Link
                            v-for="notification in page.props.notifications.items"
                            :key="notification.id"
                            :href="notification.url"
                            class="flex gap-3 border-b border-gray-50 px-4 py-3 last:border-0 hover:bg-gray-50 dark:border-gray-700/50 dark:hover:bg-gray-700/40"
                            :class="{ 'opacity-60': notification.readAt }"
                            @click="markAsRead(notification.id)"
                        >
                            <span
                                class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full"
                                :class="notification.severity === 'critical'
                                    ? 'text-red-500 bg-red-50 dark:bg-red-500/10'
                                    : 'text-orange-500 bg-orange-50 dark:bg-orange-500/10'"
                            >
                                <DynamicIcon name="exclamation-triangle" class="h-3.5 w-3.5" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ notification.message }}</p>
                                <p class="mt-0.5 text-xs text-gray-400">{{ new Date(notification.createdAt).toLocaleString() }}</p>
                            </div>
                        </Link>
                    </template>
                    <p v-else class="px-4 py-8 text-center text-sm text-gray-400">You have no notifications.</p>
                </Deferred>
            </div>
        </div>
    </div>
</template>
