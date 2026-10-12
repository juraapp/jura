<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Money from '@/Components/Money.vue';
import SegmentedControl from '@/Components/SegmentedControl.vue';
import { index as wishlistIndex } from '@/routes/wishlist';
import { projects as projectsReport } from '@/routes/reports';
import { useFormat } from '@/composables/useFormat';

const format = useFormat();

defineProps({
    projects: { type: Array, required: true },
    view: { type: String, required: true },
    views: { type: Array, required: true },
});

const changeView = (value) => {
    router.get(projectsReport.url({ query: { view: value } }), {}, { preserveScroll: true, preserveState: true });
};

const viewHints = {
    conservative: 'Estimated with confirmed income only.',
    expected: 'Estimated with confirmed and expected income.',
    optimistic: 'Estimated with all planned income.',
};

const formatMonth = (date) => new Date(`${date}T00:00:00`).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
</script>

<template>
    <Head title="Projects report" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Projects report</h2>
        </template>

        <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <EmptyState
                v-if="projects.length === 0"
                icon="flag"
                title="No wish list yet"
                description="Add items to your wish list and group them into projects to see how far along each one is."
            >
                <template #action>
                    <Link :href="wishlistIndex.url()" class="text-sm font-semibold text-primary-600 hover:underline">Go to the wish list</Link>
                </template>
            </EmptyState>

            <template v-else>
                <div>
                    <SegmentedControl :model-value="view" :options="views" @update:model-value="changeView" />
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ viewHints[view] }} Completion dates follow the planner's best order over the next 24 months.</p>
                </div>

                <article v-for="project in projects" :key="project.id ?? 'none'" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: project.color ?? '#9ca3af' }"></span>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ project.name ?? 'No project' }}</h3>
                            <span v-if="project.kindLabel" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 dark:bg-gray-700 dark:text-gray-300">{{ project.kindLabel }}</span>
                        </div>
                        <p class="text-xs text-gray-400">
                            {{ project.counts.wanted }} wanted &middot; {{ project.counts.purchased }} purchased &middot; {{ project.counts.dropped }} dropped
                        </p>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div v-for="total in project.totals" :key="total.currency">
                            <div class="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">
                                    Still wanted <span class="font-semibold text-gray-900 dark:text-white"><Money :amount="total.wanted" :currency="total.currency" /></span>
                                </span>
                                <span class="text-gray-500 dark:text-gray-400">
                                    Already bought <span class="font-semibold text-gray-900 dark:text-white"><Money :amount="total.purchased" :currency="total.currency" /></span>
                                </span>
                            </div>
                            <div v-if="total.wanted > 0" class="mt-2">
                                <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                    <div class="h-full bg-green-500" :style="{ width: total.fundedPercent + '%' }"></div>
                                </div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ format.number(total.fundedPercent, 0) }}% funded today.
                                    <template v-if="total.completion">Everything could be bought by <strong>{{ formatMonth(total.completion) }}</strong>.</template>
                                    <template v-else>Not everything can be bought within 24 months with this view of your income.</template>
                                </p>
                            </div>
                        </div>
                    </div>
                </article>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
