<script setup>
import { update as updateLocale } from '@/routes/locale';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Language switcher for the pages shown before signing in. The choice is saved in the `locale` cookie (and on the
 * account when someone is signed in); the redirect back brings the new strings, which app.js swaps in.
 */
const LANGUAGES = [
    { value: 'en', label: 'EN', name: 'English' },
    { value: 'es', label: 'ES', name: 'Español' },
];

const page = usePage();
const current = computed(() => page.props.i18n.locale);

const choose = (locale) => {
    if (locale !== current.value) {
        router.put(updateLocale.url(), { locale }, { preserveScroll: true, preserveState: true });
    }
};
</script>

<template>
    <div
        class="inline-flex rounded-full border border-gray-200 bg-white p-0.5 dark:border-gray-700 dark:bg-gray-800"
        role="group"
        :aria-label="$t('ui.common.language')"
    >
        <button
            v-for="language in LANGUAGES"
            :key="language.value"
            type="button"
            class="min-h-[40px] min-w-[44px] rounded-full px-3 text-xs font-semibold transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500"
            :class="current === language.value
                ? 'bg-primary-600 text-white'
                : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'"
            :lang="language.value"
            :title="language.name"
            :aria-label="language.name"
            :aria-pressed="current === language.value"
            @click="choose(language.value)"
        >
            {{ language.label }}
        </button>
    </div>
</template>
