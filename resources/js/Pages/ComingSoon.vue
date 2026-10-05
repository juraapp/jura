<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({
    title: {
        type: String,
        required: true,
    },
});
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
                title="This feature is under construction"
                description="We're building it in a later phase of the roadmap."
            />
        </div>
    </AuthenticatedLayout>
</template>
