<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    /** Translation key of the page title, so it follows the user's language. */
    titleKey: {
        type: String,
        required: true,
    },
});

const { t } = useI18n();
const title = computed(() => t(props.titleKey));
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ title }}
            </h2>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <EmptyState
                icon="cog"
                :title="$t('ui.common.under_construction')"
                :description="$t('ui.common.under_construction_hint')"
            />
        </div>
    </AuthenticatedLayout>
</template>
