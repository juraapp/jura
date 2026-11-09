<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ProjectModal from '@/Components/ProjectModal.vue';
import { show as showProject } from '@/actions/App/Modules/Planning/Http/Controllers/ProjectController';
import { index as wishlistIndex } from '@/routes/wishlist';
import { useFormat } from '@/composables/useFormat';

const props = defineProps({
    /** Every project with its numbers, then (id null) the items that are in none. */
    projects: { type: Array, required: true },
    projectKinds: { type: Array, required: true },
    currencies: { type: Array, required: true },
    buckets: { type: Array, required: true },
    months: { type: Number, required: true },
});

const format = useFormat();

const real = computed(() => props.projects.filter((project) => project.id !== null));
const loose = computed(() => props.projects.find((project) => project.id === null) ?? null);

/** The numbers in the project's own currency (or its first one). */
const mainTotals = (project) => project.totals.find((totals) => totals.currency === project.currency) ?? project.totals[0] ?? null;

/** What the bar shows, as percentages of the larger of the budget and what is spent and planned. */
const bar = (project, totals) => {
    const spent = totals.purchased;
    const planned = totals.wanted;
    const total = Math.max(project.budget ?? 0, spent + planned) || 1;

    return {
        spent: (spent / total) * 100,
        funded: (Math.min(totals.funded, planned) / total) * 100,
        rest: (Math.max(planned - totals.funded, 0) / total) * 100,
    };
};

const showModal = ref(false);
const editing = ref(null);
const openModal = (project = null) => {
    editing.value = project;
    showModal.value = true;
};
</script>

<template>
    <Head :title="$t('ui.projects.title')" />

    <AuthenticatedLayout width="wide">
        <template #header>
            <PageHeader :title="$t('ui.projects.title')" :subtitle="$t('ui.projects.subtitle')">
                <template #actions>
                    <PrimaryButton type="button" @click="openModal()">
                        <DynamicIcon name="plus" class="h-4 w-4" />
                        {{ $t('ui.projects.new') }}
                    </PrimaryButton>
                </template>
            </PageHeader>
        </template>

        <EmptyState
            v-if="real.length === 0 && !loose"
            icon="folder"
            :title="$t('ui.projects.empty_title')"
            :description="$t('ui.projects.empty_description')"
            :action-label="$t('ui.projects.new')"
            action-icon="plus"
            @action="openModal()"
        />

        <div v-else class="space-y-4">
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.projects.competition_hint') }}</p>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <article
                    v-for="project in real"
                    :key="project.id"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="h-3 w-3 shrink-0 rounded-full" :style="{ backgroundColor: project.color }"></span>
                            <Link :href="showProject.url(project.id)" class="truncate text-base font-semibold text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400">
                                {{ project.name }}
                            </Link>
                            <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 dark:bg-gray-700 dark:text-gray-300">{{ project.kindLabel }}</span>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                            :title="$t('ui.projects.edit')"
                            :aria-label="$t('ui.projects.edit')"
                            @click="openModal(project)"
                        >
                            <DynamicIcon name="adjustments" class="h-4 w-4" />
                        </button>
                    </div>

                    <template v-if="mainTotals(project)">
                        <div class="mt-4 flex h-2.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700" role="img" :aria-label="$t('ui.projects.items')">
                            <div class="bg-gray-500" :style="{ width: `${bar(project, mainTotals(project)).spent}%` }"></div>
                            <div class="bg-emerald-500" :style="{ width: `${bar(project, mainTotals(project)).funded}%` }"></div>
                            <div class="bg-amber-400" :style="{ width: `${bar(project, mainTotals(project)).rest}%` }"></div>
                        </div>
                        <p class="mt-1 flex flex-wrap gap-x-3 text-xs text-gray-500 dark:text-gray-400">
                            <span><span class="inline-block h-2 w-2 rounded-full bg-gray-500"></span> {{ $t('ui.projects.bought_so_far') }}</span>
                            <span><span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span> {{ $t('ui.projects.covered_by_buckets') }}</span>
                            <span><span class="inline-block h-2 w-2 rounded-full bg-amber-400"></span> {{ $t('ui.projects.still_planned') }}</span>
                        </p>

                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.projects.budget_label') }}</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ project.budget !== null ? format.money(project.budget, mainTotals(project).currency) : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.projects.planned') }}</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ format.money(mainTotals(project).wanted, mainTotals(project).currency) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.projects.spent') }}</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ format.money(mainTotals(project).purchased, mainTotals(project).currency) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.projects.left') }}</dt>
                                <dd
                                    class="font-medium"
                                    :class="mainTotals(project).budgetLeft !== null && mainTotals(project).budgetLeft < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'"
                                >
                                    {{ mainTotals(project).budgetLeft !== null ? format.money(mainTotals(project).budgetLeft, mainTotals(project).currency) : '—' }}
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                            <template v-if="mainTotals(project).wanted > 0">
                                <span v-if="mainTotals(project).completion" class="rounded-full bg-gray-100 px-2 py-1 text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                                    {{ $t('ui.projects.everything_by', { date: format.date(mainTotals(project).completion) }) }}
                                </span>
                                <span v-else class="rounded-full bg-amber-50 px-2 py-1 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                                    {{ $t('ui.projects.not_within', { months }) }}
                                </span>
                                <span
                                    v-if="mainTotals(project).onTime !== null"
                                    class="rounded-full px-2 py-1"
                                    :class="mainTotals(project).onTime ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300'"
                                >
                                    {{ mainTotals(project).onTime ? $t('ui.projects.on_time') : $t('ui.projects.late') }}
                                </span>
                            </template>
                            <span v-else class="text-gray-400">{{ $t('ui.projects.no_wanted') }}</span>
                            <span v-if="project.targetDate" class="text-gray-500 dark:text-gray-400">{{ $t('ui.projects.target_on', { date: format.date(project.targetDate) }) }}</span>
                            <span v-if="project.bucket" class="rounded-full bg-primary-50 px-2 py-1 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                                {{ project.bucket.name }} · {{ format.money(project.bucket.envelope, mainTotals(project).currency) }}
                            </span>
                        </div>
                        <p v-if="mainTotals(project).neededPerMonth" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ $t('ui.projects.needed_per_month', { amount: format.money(mainTotals(project).neededPerMonth, mainTotals(project).currency) }) }}
                        </p>
                    </template>
                    <p v-else class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ $t('ui.projects.no_items') }}</p>
                </article>

                <article
                    v-if="loose"
                    class="rounded-xl border border-dashed border-gray-300 bg-white p-5 dark:border-gray-600 dark:bg-gray-800"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ $t('ui.projects.not_in_project') }}</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.projects.not_in_project_hint') }}</p>
                        </div>
                        <Link :href="wishlistIndex.url()" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $t('ui.projects.open_wishlist') }}</Link>
                    </div>
                    <dl v-for="totals in loose.totals" :key="totals.currency" class="mt-3 grid grid-cols-3 gap-3 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.projects.planned') }}</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ format.money(totals.wanted, totals.currency) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.projects.funded') }}</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ totals.fundedPercent }}%</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.projects.spent') }}</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ format.money(totals.purchased, totals.currency) }}</dd>
                        </div>
                    </dl>
                </article>
            </div>
        </div>

        <ProjectModal
            :show="showModal"
            :project="editing"
            :kinds="projectKinds"
            :currencies="currencies"
            :buckets="buckets"
            :projects="projects"
            @close="showModal = false"
        />
    </AuthenticatedLayout>
</template>
