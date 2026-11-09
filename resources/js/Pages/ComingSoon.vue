<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
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

    <AuthenticatedLayout width="wide">
        <template #header>
            <PageHeader :title="title" />
        </template>

        <div>
            <EmptyState
                icon="cog"
                :title="$t('ui.common.under_construction')"
                :description="$t('ui.common.under_construction_hint')"
            />
        </div>
    </AuthenticatedLayout>
</template>
