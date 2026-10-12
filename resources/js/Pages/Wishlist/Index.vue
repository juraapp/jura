<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Modal from '@/Components/Modal.vue';
import ModalForm from '@/Components/ModalForm.vue';
import Money from '@/Components/Money.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { formatMoney } from '@/chartPalette';
import { index as bucketsIndex } from '@/routes/buckets';
import {
    store,
    update,
    destroy,
    purchase,
    unpurchase,
} from '@/actions/App/Modules/Planning/Http/Controllers/WishlistItemController';
import {
    store as storeProject,
    update as updateProject,
    destroy as destroyProject,
} from '@/actions/App/Modules/Planning/Http/Controllers/ProjectController';
import { useFormat } from '@/composables/useFormat';

const format = useFormat();

const props = defineProps({
    projects: { type: Array, required: true },
    items: { type: Array, required: true },
    buckets: { type: Array, required: true },
    accounts: { type: Array, required: true },
    categories: { type: Array, required: true },
    currencies: { type: Array, required: true },
    projectKinds: { type: Array, required: true },
});

const page = usePage();
const locale = () => page.props.auth.user?.locale ?? 'en';
const money = (value, currency) => formatMoney(value, currency, locale());
const formatDate = (date) => format.date(date);
const today = new Date().toISOString().slice(0, 10);

const showClosed = ref(false);

const groups = computed(() => {
    const visible = props.items.filter((item) => showClosed.value || item.status === 'wanted');
    const byProject = (projectId) => visible
        .filter((item) => item.projectId === projectId)
        .sort((a, b) => b.importance - a.importance || (a.desiredBy ?? '9999').localeCompare(b.desiredBy ?? '9999') || a.id - b.id);

    const result = props.projects.map((project) => ({ project, items: byProject(project.id) }));
    const loose = byProject(null);
    if (loose.length || props.projects.length === 0) {
        result.push({ project: null, items: loose });
    }

    return result.filter((group) => group.items.length || group.project);
});

const totalsByCurrency = (items) => {
    const totals = {};
    items.filter((item) => item.status === 'wanted').forEach((item) => {
        totals[item.currency] = (totals[item.currency] ?? 0) + item.price;
    });

    return Object.entries(totals).map(([currency, amount]) => money(amount, currency)).join(' + ');
};

/* ---- project modal ---- */
const showProjectModal = ref(false);
const editingProject = ref(null);
const projectForm = useForm({ name: '', kind: 'project', color: '#2563eb', notes: '' });

const openProjectModal = (project = null) => {
    editingProject.value = project;
    projectForm.clearErrors();
    projectForm.name = project?.name ?? '';
    projectForm.kind = project?.kind ?? 'project';
    projectForm.color = project?.color ?? '#2563eb';
    projectForm.notes = project?.notes ?? '';
    showProjectModal.value = true;
};

const submitProject = () => {
    const action = editingProject.value ? updateProject(editingProject.value.id) : storeProject();
    projectForm.submit(action, { preserveScroll: true, onSuccess: () => (showProjectModal.value = false) });
};

const confirmingDeleteProject = ref(null);
const deleteProject = () => {
    router.delete(destroyProject.url(confirmingDeleteProject.value.id), {
        preserveScroll: true,
        onFinish: () => (confirmingDeleteProject.value = null),
    });
};

/* ---- item modal ---- */
const showItemModal = ref(false);
const editingItem = ref(null);

const blankItem = (projectId = '') => ({
    name: '',
    price: '',
    currency: props.currencies[0],
    importance: 3,
    project_id: projectId,
    bucket_id: '',
    desired_by: '',
    allowed_account_ids: [],
    url: '',
    notes: '',
    status: 'wanted',
});

const itemForm = useForm(blankItem());
const bucketChoices = computed(() => props.buckets.filter((bucket) => bucket.currency === itemForm.currency));

const openItemModal = (item = null, projectId = '') => {
    editingItem.value = item;
    itemForm.clearErrors();
    const values = item
        ? {
            name: item.name,
            price: String(item.price),
            currency: item.currency,
            importance: item.importance,
            project_id: item.projectId ?? '',
            bucket_id: item.bucket?.id ?? '',
            desired_by: item.desiredBy ?? '',
            allowed_account_ids: [...item.allowedAccountIds],
            url: item.url ?? '',
            notes: item.notes ?? '',
            status: item.status === 'dropped' ? 'dropped' : 'wanted',
        }
        : blankItem(projectId);
    Object.entries(values).forEach(([key, value]) => (itemForm[key] = value));
    showItemModal.value = true;
};

const submitItem = () => {
    const action = editingItem.value ? update(editingItem.value.id) : store();
    itemForm.submit(action, { preserveScroll: true, onSuccess: () => (showItemModal.value = false) });
};

const setStatus = (item, status) => {
    router.patch(update.url(item.id), {
        name: item.name,
        price: item.price,
        currency: item.currency,
        importance: item.importance,
        project_id: item.projectId,
        bucket_id: item.bucket?.id ?? null,
        desired_by: item.desiredBy,
        allowed_account_ids: item.allowedAccountIds,
        url: item.url,
        notes: item.notes,
        status,
    }, { preserveScroll: true });
};

const confirmingDeleteItem = ref(null);
const deleteItem = () => {
    router.delete(destroy.url(confirmingDeleteItem.value.id), {
        preserveScroll: true,
        onFinish: () => (confirmingDeleteItem.value = null),
    });
};

/* ---- purchase ---- */
const buying = ref(null);
const purchaseForm = useForm({ account_id: '', category_id: '', amount: '', date: today });

const accountChoices = computed(() => {
    if (!buying.value) return [];
    const bucket = props.buckets.find((candidate) => candidate.id === buying.value.bucket?.id);
    const restrictions = [buying.value.allowedAccountIds, bucket?.allowed_account_ids ?? []].filter((list) => list.length);

    return props.accounts.filter((account) => restrictions.every((list) => list.includes(account.id)));
});

const openPurchase = (item) => {
    buying.value = item;
    purchaseForm.clearErrors();
    purchaseForm.account_id = '';
    purchaseForm.category_id = '';
    purchaseForm.amount = String(item.price);
    purchaseForm.date = today;
};

const submitPurchase = () => {
    purchaseForm.submit(purchase(buying.value.id), { preserveScroll: true, onSuccess: () => (buying.value = null) });
};

const undo = (item) => router.post(unpurchase.url(item.id), {}, { preserveScroll: true });
</script>

<template>
    <Head title="Wish list" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Wish list</h2>
                <div class="flex items-center gap-2">
                    <SecondaryButton type="button" @click="openProjectModal()">
                        <DynamicIcon name="flag" class="mr-1.5 h-4 w-4" />
                        New project
                    </SecondaryButton>
                    <PrimaryButton type="button" @click="openItemModal()">
                        <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                        New item
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
            <EmptyState
                v-if="items.length === 0 && projects.length === 0"
                icon="flag"
                title="Everything you want, in one place"
                description="Add the things you would like to get, group them into projects or hobbies, and rate how much you want each one. Link an item to a bucket to see how funded it is."
            >
                <template #action>
                    <PrimaryButton type="button" @click="openItemModal()">
                        <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                        Add my first item
                    </PrimaryButton>
                </template>
            </EmptyState>

            <template v-else>
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <input v-model="showClosed" type="checkbox" class="rounded border-gray-300 text-primary-600" />
                    Show purchased and dropped items
                </label>

                <section v-for="group in groups" :key="group.project?.id ?? 'none'" class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: group.project?.color ?? '#9ca3af' }"></span>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ group.project?.name ?? 'No project' }}</h3>
                            <span v-if="group.project" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 dark:bg-gray-700 dark:text-gray-300">{{ group.project.kindLabel }}</span>
                            <span v-if="totalsByCurrency(group.items)" class="text-xs text-gray-400">&middot; {{ totalsByCurrency(group.items) }} wanted</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-500/10" @click="openItemModal(null, group.project?.id ?? '')">
                                + Add item
                            </button>
                            <template v-if="group.project">
                                <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" title="Edit project" @click="openProjectModal(group.project)">
                                    <DynamicIcon name="pencil" class="h-4 w-4" />
                                </button>
                                <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" title="Delete project" @click="confirmingDeleteProject = group.project">
                                    <DynamicIcon name="trash" class="h-4 w-4" />
                                </button>
                            </template>
                        </div>
                    </div>

                    <p v-if="group.project?.notes" class="text-xs text-gray-500 dark:text-gray-400">{{ group.project.notes }}</p>

                    <p v-if="group.items.length === 0" class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-400 dark:border-gray-700">
                        Nothing here yet.
                    </p>

                    <ul v-else class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                        <li v-for="item in group.items" :key="item.id" class="px-4 py-3" :class="{ 'opacity-60': item.status !== 'wanted' }">
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="flex items-center gap-2 text-sm font-medium text-gray-900 dark:text-white">
                                        <span class="truncate" :class="{ 'line-through': item.status === 'dropped' }">{{ item.name }}</span>
                                        <a v-if="item.url" :href="item.url" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-primary-600" title="Open link">
                                            <DynamicIcon name="link" class="h-3.5 w-3.5" />
                                        </a>
                                        <span v-if="item.status !== 'wanted'" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                            {{ item.statusLabel }}<template v-if="item.purchasedOn"> {{ formatDate(item.purchasedOn) }}</template>
                                        </span>
                                    </p>
                                    <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-400">
                                        <span class="inline-flex items-center gap-0.5" :title="`Importance ${item.importance} of 5`">
                                            <span v-for="n in 5" :key="n" class="h-1.5 w-1.5 rounded-full" :class="n <= item.importance ? 'bg-primary-500' : 'bg-gray-200 dark:bg-gray-600'"></span>
                                        </span>
                                        <span v-if="item.desiredBy">by {{ formatDate(item.desiredBy) }}</span>
                                        <button v-if="item.bucket" type="button" class="font-medium text-primary-600 hover:underline dark:text-primary-400" @click="router.visit(bucketsIndex.url())">
                                            {{ item.bucket.name }}
                                        </button>
                                        <span v-if="item.allowedAccountIds.length">
                                            only with:
                                            <span v-for="id in item.allowedAccountIds" :key="id" class="ml-1 rounded-full bg-gray-100 px-1.5 py-0.5 dark:bg-gray-700">{{ accounts.find((a) => a.id === id)?.name ?? '?' }}</span>
                                        </span>
                                    </p>
                                    <div v-if="item.status === 'wanted' && item.fundedPercent !== null" class="mt-2 flex items-center gap-2">
                                        <div class="h-1.5 w-40 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                            <div class="h-full bg-green-500" :style="{ width: item.fundedPercent + '%' }"></div>
                                        </div>
                                        <span class="text-xs text-gray-400">{{ format.number(item.fundedPercent, 0) }}% funded</span>
                                    </div>
                                </div>

                                <p class="text-right text-sm font-semibold tabular-nums text-gray-900 dark:text-white"><Money :amount="item.price" :currency="item.currency" /></p>

                                <div class="flex items-center gap-1">
                                    <template v-if="item.status === 'wanted'">
                                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-green-50 hover:text-green-600 dark:hover:bg-green-500/10" title="Mark as purchased" @click="openPurchase(item)">
                                            <DynamicIcon name="check-circle" class="h-4 w-4" />
                                        </button>
                                        <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" title="Drop (no longer wanted)" @click="setStatus(item, 'dropped')">
                                            <DynamicIcon name="forward" class="h-4 w-4" />
                                        </button>
                                    </template>
                                    <button v-else-if="item.status === 'dropped'" type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" title="Want it again" @click="setStatus(item, 'wanted')">
                                        <DynamicIcon name="arrow-path" class="h-4 w-4" />
                                    </button>
                                    <button v-else type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" title="Undo the purchase (removes the transaction)" @click="undo(item)">
                                        Undo purchase
                                    </button>
                                    <button v-if="item.status !== 'purchased'" type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" title="Edit" @click="openItemModal(item)">
                                        <DynamicIcon name="pencil" class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" title="Delete" @click="confirmingDeleteItem = item">
                                        <DynamicIcon name="trash" class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </li>
                    </ul>
                </section>
            </template>
        </div>

        <ModalForm :show="showProjectModal" :title="editingProject ? 'Edit project' : 'New project'" @close="showProjectModal = false">
            <form id="project-form" class="space-y-4" @submit.prevent="submitProject">
                <div>
                    <InputLabel for="project_name" value="Name" />
                    <TextInput id="project_name" v-model="projectForm.name" type="text" class="mt-1 block w-full" placeholder="Home studio, Cycling, Gifts..." />
                    <InputError class="mt-1" :message="projectForm.errors.name" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="project_kind" value="Type" />
                        <SelectInput id="project_kind" v-model="projectForm.kind">
                            <option v-for="kind in projectKinds" :key="kind.value" :value="kind.value">{{ kind.label }}</option>
                        </SelectInput>
                        <InputError class="mt-1" :message="projectForm.errors.kind" />
                    </div>
                    <div>
                        <InputLabel for="project_color" value="Colour" />
                        <input id="project_color" v-model="projectForm.color" type="color" class="mt-1 h-10 w-full rounded-lg border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-700" />
                        <InputError class="mt-1" :message="projectForm.errors.color" />
                    </div>
                </div>
                <div>
                    <InputLabel for="project_notes" value="Notes (optional)" />
                    <TextInput id="project_notes" v-model="projectForm.notes" type="text" class="mt-1 block w-full" />
                </div>
            </form>
            <template #footer>
                <SecondaryButton type="button" @click="showProjectModal = false">Cancel</SecondaryButton>
                <PrimaryButton form="project-form" type="submit" :disabled="projectForm.processing">Save</PrimaryButton>
            </template>
        </ModalForm>

        <ModalForm :show="showItemModal" :title="editingItem ? 'Edit item' : 'New wish list item'" @close="showItemModal = false">
            <form id="item-form" class="space-y-4" @submit.prevent="submitItem">
                <div>
                    <InputLabel for="item_name" value="What do you want?" />
                    <TextInput id="item_name" v-model="itemForm.name" type="text" class="mt-1 block w-full" placeholder="Road bike, camera, course..." />
                    <InputError class="mt-1" :message="itemForm.errors.name" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="item_price" value="Price" />
                        <TextInput id="item_price" v-model="itemForm.price" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="itemForm.errors.price" />
                    </div>
                    <div>
                        <InputLabel for="item_currency" value="Currency" />
                        <SelectInput id="item_currency" v-model="itemForm.currency">
                            <option v-for="currency in currencies" :key="currency" :value="currency">{{ currency }}</option>
                        </SelectInput>
                        <InputError class="mt-1" :message="itemForm.errors.currency" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="item_importance" value="How much do you want it?" />
                        <SelectInput id="item_importance" v-model="itemForm.importance">
                            <option :value="5">5 - Really want it</option>
                            <option :value="4">4</option>
                            <option :value="3">3 - Would be nice</option>
                            <option :value="2">2</option>
                            <option :value="1">1 - If there is spare money</option>
                        </SelectInput>
                        <InputError class="mt-1" :message="itemForm.errors.importance" />
                    </div>
                    <div>
                        <InputLabel for="item_project" value="Project" />
                        <SelectInput id="item_project" v-model="itemForm.project_id">
                            <option value="">No project</option>
                            <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                        </SelectInput>
                        <InputError class="mt-1" :message="itemForm.errors.project_id" />
                    </div>
                </div>

                <details class="rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700" :open="Boolean(itemForm.bucket_id) || itemForm.allowed_account_ids.length > 0">
                    <summary class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-200">Funding, dates and limits</summary>
                    <div class="mt-3 space-y-4">
                        <div>
                            <InputLabel for="item_bucket" value="Pay for it from bucket" />
                            <SelectInput id="item_bucket" v-model="itemForm.bucket_id">
                                <option value="">No bucket</option>
                                <option v-for="bucket in bucketChoices" :key="bucket.id" :value="bucket.id">{{ bucket.name }}</option>
                            </SelectInput>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Only savings buckets in the same currency. Create one in Buckets first.</p>
                            <InputError class="mt-1" :message="itemForm.errors.bucket_id" />
                        </div>
                        <div>
                            <InputLabel for="item_desired_by" value="Want it by (optional)" />
                            <TextInput id="item_desired_by" v-model="itemForm.desired_by" type="date" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="itemForm.errors.desired_by" />
                        </div>
                        <fieldset v-if="accounts.length">
                            <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">Can only be bought with</legend>
                            <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Leave all unchecked to allow any account.</p>
                            <label v-for="account in accounts" :key="account.id" class="flex items-center gap-2 py-0.5 text-sm text-gray-700 dark:text-gray-200">
                                <input v-model="itemForm.allowed_account_ids" type="checkbox" :value="account.id" class="rounded border-gray-300 text-primary-600" />
                                {{ account.name }}
                            </label>
                            <InputError class="mt-1" :message="itemForm.errors['allowed_account_ids.0']" />
                        </fieldset>
                        <div>
                            <InputLabel for="item_url" value="Link (optional)" />
                            <TextInput id="item_url" v-model="itemForm.url" type="url" class="mt-1 block w-full" placeholder="https://..." />
                            <InputError class="mt-1" :message="itemForm.errors.url" />
                        </div>
                        <div>
                            <InputLabel for="item_notes" value="Notes (optional)" />
                            <TextInput id="item_notes" v-model="itemForm.notes" type="text" class="mt-1 block w-full" />
                        </div>
                    </div>
                </details>
            </form>
            <template #footer>
                <SecondaryButton type="button" @click="showItemModal = false">Cancel</SecondaryButton>
                <PrimaryButton form="item-form" type="submit" :disabled="itemForm.processing">Save</PrimaryButton>
            </template>
        </ModalForm>

        <ModalForm
            :show="buying !== null"
            :title="buying ? `Buy: ${buying.name}` : 'Mark as purchased'"
            description="This records a real expense and, if the item has a bucket, spends from it."
            @close="buying = null"
        >
            <form id="purchase-form" class="space-y-4" @submit.prevent="submitPurchase">
                <div>
                    <InputLabel for="purchase_account" value="Paid with" />
                    <SelectInput id="purchase_account" v-model="purchaseForm.account_id">
                        <option value="">Select an account</option>
                        <option v-for="account in accountChoices" :key="account.id" :value="account.id">{{ account.name }}</option>
                    </SelectInput>
                    <p v-if="buying && accountChoices.length === 0" class="mt-1 text-xs text-red-600">No account is allowed for this item and its bucket.</p>
                    <InputError class="mt-1" :message="purchaseForm.errors.account_id" />
                </div>
                <div>
                    <InputLabel for="purchase_category" value="Category" />
                    <SelectInput id="purchase_category" v-model="purchaseForm.category_id">
                        <option value="">Select a category</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                    </SelectInput>
                    <InputError class="mt-1" :message="purchaseForm.errors.category_id" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="purchase_amount" value="Final price" />
                        <TextInput id="purchase_amount" v-model="purchaseForm.amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="purchaseForm.errors.amount" />
                    </div>
                    <div>
                        <InputLabel for="purchase_date" value="Date" />
                        <TextInput id="purchase_date" v-model="purchaseForm.date" type="date" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="purchaseForm.errors.date" />
                    </div>
                </div>
            </form>
            <template #footer>
                <SecondaryButton type="button" @click="buying = null">Cancel</SecondaryButton>
                <PrimaryButton form="purchase-form" type="submit" :disabled="purchaseForm.processing">Record purchase</PrimaryButton>
            </template>
        </ModalForm>

        <Modal :show="confirmingDeleteItem !== null" @close="confirmingDeleteItem = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delete this item?</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">A purchase already recorded as a transaction is not removed. Use "Undo purchase" first if you want that.</p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="confirmingDeleteItem = null">Cancel</SecondaryButton>
                    <DangerButton type="button" @click="deleteItem">Delete</DangerButton>
                </div>
            </div>
        </Modal>

        <Modal :show="confirmingDeleteProject !== null" @close="confirmingDeleteProject = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delete this project?</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Its items are kept and move to "No project".</p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="confirmingDeleteProject = null">Cancel</SecondaryButton>
                    <DangerButton type="button" @click="deleteProject">Delete</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
