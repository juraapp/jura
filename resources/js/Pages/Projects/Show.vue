<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import Modal from '@/Components/Modal.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ProjectModal from '@/Components/ProjectModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatCard from '@/Components/StatCard.vue';
import { destroy as destroyProject, index as projectsIndex } from '@/actions/App/Modules/Planning/Http/Controllers/ProjectController';
import { index as bucketsIndex } from '@/routes/buckets';
import { index as wishlistIndex } from '@/routes/wishlist';
import { useFormat } from '@/composables/useFormat';

const props = defineProps({
    /** The project with its numbers and `items`. */
    project: { type: Object, required: true },
    projects: { type: Array, required: true },
    projectKinds: { type: Array, required: true },
    currencies: { type: Array, required: true },
    buckets: { type: Array, required: true },
    months: { type: Number, required: true },
});

const format = useFormat();

const totals = computed(() => props.project.totals.find((row) => row.currency === props.project.currency) ?? props.project.totals[0] ?? null);
const currency = computed(() => totals.value?.currency ?? props.project.currency);
const money = (value) => format.money(value, currency.value);

const statusClasses = {
    wanted: 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300',
    purchased: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
    dropped: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-300',
};

const showEdit = ref(false);

const confirmingDelete = ref(false);
const deleteBucket = ref(false);
const deleteProject = () => {
    router.delete(destroyProject.url(props.project.id), {
        data: { delete_bucket: deleteBucket.value },
        onFinish: () => (confirmingDelete.value = false),
    });
};
</script>

<template>
    <Head :title="project.name" />

    <AuthenticatedLayout width="wide">
        <template #header>
            <PageHeader :title="project.name" :subtitle="project.kindLabel" :back="{ href: projectsIndex.url(), label: $t('ui.projects.back') }">
                <template #actions>
                    <SecondaryButton type="button" @click="showEdit = true">{{ $t('ui.projects.edit') }}</SecondaryButton>
                    <PrimaryButton :as="Link" :href="wishlistIndex.url({ query: { project: project.id } })">
                        <DynamicIcon name="plus" class="h-4 w-4" />
                        {{ $t('ui.projects.new_item') }}
                    </PrimaryButton>
                    <DangerButton type="button" @click="confirmingDelete = true">{{ $t('ui.projects.delete') }}</DangerButton>
                </template>
            </PageHeader>
        </template>

        <div class="space-y-6">
            <div v-if="totals" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard :label="$t('ui.projects.budget_label')" icon="banknotes">
                    {{ project.budget !== null ? money(project.budget) : '—' }}
                    <template v-if="totals.budgetLeft !== null" #footer>
                        <span :class="totals.budgetLeft < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400'">
                            {{ totals.budgetLeft < 0 ? $t('ui.projects.over_budget', { amount: money(-totals.budgetLeft) }) : `${$t('ui.projects.left')}: ${money(totals.budgetLeft)}` }}
                        </span>
                    </template>
                </StatCard>
                <StatCard :label="$t('ui.projects.planned')" icon="flag">
                    {{ money(totals.wanted) }}
                    <template #footer>
                        <span class="text-gray-500 dark:text-gray-400">{{ project.counts.wanted }} {{ $t('ui.projects.wanted') }}</span>
                    </template>
                </StatCard>
                <StatCard :label="$t('ui.projects.funded')" icon="chart-pie">
                    {{ money(totals.funded) }}
                    <template #footer>
                        <span class="text-gray-500 dark:text-gray-400">{{ totals.fundedPercent }}%</span>
                    </template>
                </StatCard>
                <StatCard :label="$t('ui.projects.spent')" icon="wallet">
                    {{ money(totals.purchased) }}
                    <template #footer>
                        <span class="text-gray-500 dark:text-gray-400">{{ project.counts.purchased }} {{ $t('ui.projects.purchased') }}</span>
                    </template>
                </StatCard>
            </div>

            <div v-if="totals" class="flex flex-wrap items-center gap-2 text-sm">
                <template v-if="totals.wanted > 0">
                    <span v-if="totals.completion" class="rounded-full bg-gray-100 px-3 py-1 text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                        {{ $t('ui.projects.everything_by', { date: format.date(totals.completion) }) }}
                    </span>
                    <span v-else class="rounded-full bg-amber-50 px-3 py-1 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">{{ $t('ui.projects.not_within', { months }) }}</span>
                    <span
                        v-if="totals.onTime !== null"
                        class="rounded-full px-3 py-1"
                        :class="totals.onTime ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300'"
                    >
                        {{ totals.onTime ? $t('ui.projects.on_time') : $t('ui.projects.late') }}
                    </span>
                </template>
                <span v-if="project.targetDate" class="text-gray-500 dark:text-gray-400">{{ $t('ui.projects.target_on', { date: format.date(project.targetDate) }) }}</span>
            </div>
            <p v-if="totals?.neededPerMonth" class="text-sm text-gray-600 dark:text-gray-300">{{ $t('ui.projects.needed_per_month', { amount: money(totals.neededPerMonth) }) }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.projects.competition_hint') }}</p>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $t('ui.projects.bucket_panel') }}</h2>
                <div v-if="project.bucket" class="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span class="text-gray-700 dark:text-gray-200">{{ project.bucket.name }} · {{ $t('ui.projects.bucket_holds', { amount: money(project.bucket.envelope) }) }}</span>
                    <Link :href="bucketsIndex.url()" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $t('ui.shell.nav.buckets') }}</Link>
                </div>
                <p v-else class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $t('ui.projects.bucket_hint') }}</p>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $t('ui.projects.items') }}</h2>
                    <Link :href="wishlistIndex.url()" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $t('ui.projects.manage_items') }}</Link>
                </div>

                <p v-if="project.items.length === 0" class="px-5 py-6 text-sm text-gray-500 dark:text-gray-400">{{ $t('ui.projects.no_items') }}</p>
                <ul v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                    <li v-for="item in project.items" :key="item.id" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ item.name }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                <template v-if="item.status === 'purchased'">
                                    {{ $t('ui.projects.bought_on', { date: format.date(item.purchasedOn) }) }} · {{ $t('ui.projects.paid', { amount: format.money(item.paid, item.currency) }) }}
                                </template>
                                <template v-else-if="item.status === 'wanted'">
                                    <template v-if="item.purchaseDate">{{ $t('ui.projects.buy_on', { date: format.date(item.purchaseDate) }) }}</template>
                                    <template v-else>{{ $t('ui.projects.not_in_plan') }}</template>
                                    · {{ item.bucket ? `${item.bucket.name} (${item.bucket.fromProject ? $t('ui.projects.from_project_bucket') : item.fundedPercent + '%'})` : $t('ui.projects.from_ready') }}
                                </template>
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ format.money(item.price, item.currency) }}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="statusClasses[item.status]">{{ item.statusLabel }}</span>
                        </div>
                    </li>
                </ul>
            </section>

            <p v-if="project.notes" class="text-sm text-gray-600 dark:text-gray-300">{{ project.notes }}</p>
        </div>

        <ProjectModal
            :show="showEdit"
            :project="project"
            :kinds="projectKinds"
            :currencies="currencies"
            :buckets="buckets"
            :projects="projects"
            @close="showEdit = false"
        />

        <Modal :show="confirmingDelete" @close="confirmingDelete = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $t('ui.projects.delete_title') }}</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $t('ui.projects.delete_hint') }}</p>
                <label v-if="project.bucket" class="mt-4 flex items-start gap-2 text-sm text-gray-700 dark:text-gray-200">
                    <input v-model="deleteBucket" type="checkbox" class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700" />
                    {{ $t('ui.projects.delete_bucket_too') }}
                </label>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="confirmingDelete = false">{{ $t('ui.common.cancel') }}</SecondaryButton>
                    <DangerButton type="button" @click="deleteProject">{{ $t('ui.common.delete') }}</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
