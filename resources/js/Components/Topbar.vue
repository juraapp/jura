<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import NetWorthBadge from '@/Components/NetWorthBadge.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import { useDarkMode } from '@/composables/useDarkMode';
import { useSidebar } from '@/composables/useSidebar';
import { useTransactionModal } from '@/composables/useTransactionModal';
import { logout } from '@/routes';
import { edit as preferencesEdit } from '@/routes/preferences';
import { edit as profileEdit } from '@/routes/profile';

const page = usePage();
const { toggle: toggleSidebar } = useSidebar();
const { isDark, toggle: toggleDarkMode } = useDarkMode();
const { open: openTransactionModal } = useTransactionModal();

const initial = ref(page.props.auth.user.name.charAt(0).toUpperCase());
</script>

<template>
    <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-gray-200 bg-white/80 px-4 backdrop-blur sm:px-6 dark:border-gray-700 dark:bg-gray-800/80">
        <button
            type="button"
            class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 lg:hidden dark:text-gray-400 dark:hover:bg-gray-700"
            @click="toggleSidebar"
        >
            <DynamicIcon name="bars-3" class="h-6 w-6" />
            <span class="sr-only">Open menu</span>
        </button>

        <NetWorthBadge />

        <div class="ms-auto flex items-center gap-2">
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:focus:ring-primary-800"
                @click="openTransactionModal(null, 'expense')"
            >
                <DynamicIcon name="plus" class="h-4 w-4" />
                <span class="hidden sm:inline">New transaction</span>
            </button>

            <button
                type="button"
                class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
                @click="toggleDarkMode"
            >
                <DynamicIcon v-if="isDark" name="sun" class="h-5 w-5" />
                <DynamicIcon v-else name="moon" class="h-5 w-5" />
                <span class="sr-only">Toggle theme</span>
            </button>

            <NotificationBell />

            <Dropdown align="right" width="56">
                <template #trigger>
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700"
                    >
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700 dark:bg-primary-500/20 dark:text-primary-400">
                            {{ initial }}
                        </span>
                        <DynamicIcon name="chevron-down" class="hidden h-4 w-4 text-gray-400 sm:block" />
                    </button>
                </template>

                <template #content>
                    <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ page.props.auth.user.name }}</p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ page.props.auth.user.email }}</p>
                    </div>

                    <DropdownLink :href="profileEdit.url()">Profile & security</DropdownLink>
                    <DropdownLink :href="preferencesEdit.url()">Preferences</DropdownLink>

                    <Link
                        :href="logout()"
                        as="button"
                        class="block w-full px-4 py-2 text-start text-sm text-red-600 hover:bg-gray-100 dark:text-red-400 dark:hover:bg-gray-700"
                    >
                        Log out
                    </Link>
                </template>
            </Dropdown>
        </div>
    </header>
</template>
