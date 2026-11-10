<script setup>
import logoIcon from '../../img/logo-icon.png';
import AuthIllustration from '@/Components/AuthIllustration.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import { useDarkMode } from '@/composables/useDarkMode';
import { Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    illustration: { type: String, default: 'wallet' },
});

const { isDark, toggle } = useDarkMode();
</script>

<template>
    <div class="flex min-h-screen bg-white text-gray-900 dark:bg-gray-900 dark:text-gray-100">
        <aside class="relative hidden w-[44%] max-w-[42rem] shrink-0 flex-col overflow-hidden bg-primary-900 p-12 text-white lg:flex xl:p-16 dark:bg-primary-950">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0">
                <div class="absolute -right-24 -top-24 h-80 w-80 rounded-full bg-primary-800/70 dark:bg-primary-900/50"></div>
                <div class="absolute -left-20 bottom-1/4 h-72 w-40 rounded-r-[5rem] bg-primary-800/50 dark:bg-primary-900/30"></div>
                <div class="absolute -bottom-32 -right-20 h-96 w-96 rounded-full bg-primary-950/60 dark:bg-primary-900/30"></div>
            </div>

            <Link href="/" class="relative flex w-fit items-center gap-3 rounded-2xl focus:outline-none focus:ring-2 focus:ring-primary-300 focus:ring-offset-4 focus:ring-offset-primary-900">
                <img :src="logoIcon" alt="" class="h-11 w-auto rounded-xl" />
                <span class="text-2xl font-semibold tracking-tight">Jura</span>
            </Link>

            <div aria-hidden="true" class="relative flex flex-1 items-center justify-center">
                <AuthIllustration :variant="illustration" />
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex items-center justify-between px-4 py-4 sm:px-8">
                <Link href="/" class="flex items-center gap-2.5 rounded-2xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-4 lg:invisible dark:focus:ring-offset-gray-900">
                    <img :src="logoIcon" alt="" class="h-9 w-auto rounded-lg" />
                    <span class="text-xl font-semibold tracking-tight text-primary-800 dark:text-primary-300">Jura</span>
                </Link>

                <button
                    type="button"
                    class="flex h-11 w-11 items-center justify-center rounded-full text-gray-500 transition-colors duration-200 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-400 dark:hover:bg-gray-800"
                    :aria-label="$t('ui.shell.topbar.toggle_theme')"
                    :aria-pressed="isDark"
                    @click="toggle"
                >
                    <DynamicIcon :name="isDark ? 'sun' : 'moon'" class="h-5 w-5" aria-hidden="true" />
                </button>
            </header>

            <main class="flex flex-1 items-center justify-center px-4 pb-12 pt-2 sm:px-8">
                <section aria-labelledby="auth-title" class="auth-enter w-full max-w-md">
                    <div class="mb-8">
                        <h1 id="auth-title" class="text-3xl font-bold leading-tight tracking-tight text-gray-900 dark:text-white">{{ title }}</h1>
                        <p v-if="description" class="mt-3 text-base leading-7 text-gray-500 dark:text-gray-400">{{ description }}</p>
                    </div>

                    <slot />

                    <footer v-if="$slots.footer" class="auth-footer mt-8 text-center text-sm text-gray-600 dark:text-gray-400">
                        <slot name="footer" />
                    </footer>
                </section>
            </main>
        </div>
    </div>
</template>

<style scoped>
.auth-footer :deep(a),
.auth-footer :deep(button) {
    @apply inline-flex min-h-[44px] items-center rounded-md font-semibold text-primary-700 hover:text-primary-800 hover:underline focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:text-primary-300 dark:hover:text-primary-200 dark:focus:ring-offset-gray-900;
}

.auth-enter :deep(input[type='checkbox']) {
    @apply h-5 w-5 rounded-md border-gray-300 bg-white text-primary-600 focus:ring-2 focus:ring-primary-200 dark:border-gray-500 dark:bg-gray-700 dark:focus:ring-primary-800;
}

@media (prefers-reduced-motion: no-preference) {
    .auth-enter {
        animation: auth-enter 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
}

@keyframes auth-enter {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}
</style>
