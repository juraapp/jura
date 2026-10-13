<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CategoryIcon from '@/Components/CategoryIcon.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ModalForm from '@/Components/ModalForm.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import BulkCheckbox from '@/Components/BulkCheckbox.vue';
import BulkDeleteBar from '@/Components/BulkDeleteBar.vue';
import { useBulkSelection } from '@/composables/useBulkSelection';
import { store, update, destroy, bulkDestroy } from '@/actions/App/Modules/Ledger/Http/Controllers/CategoryController';

const props = defineProps({
    expenseCategories: { type: Array, required: true },
    incomeCategories: { type: Array, required: true },
    iconChoices: { type: Array, required: true },
});

const { t } = useI18n();

const editableCategories = computed(() => [...props.expenseCategories, ...props.incomeCategories].filter((category) => category.editable));
const bulk = useBulkSelection(() => editableCategories.value);
watch(() => [props.expenseCategories, props.incomeCategories], () => bulk.clear());
const showModal = ref(false);
const editingCategory = ref(null);
const confirmingDeleteCategory = ref(null);

const form = useForm({
    name: '',
    type: 'expense',
    icon: 'tag',
    color: '#6b7280',
});

const openCreateModal = () => {
    editingCategory.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (category) => {
    editingCategory.value = category;
    form.clearErrors();
    form.name = category.name;
    form.type = category.type;
    form.icon = category.icon;
    form.color = category.color;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const submit = () => {
    if (editingCategory.value) {
        form.submit(update(editingCategory.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.submit(store(), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

const confirmDelete = (category) => {
    confirmingDeleteCategory.value = category;
};

const deleteCategory = () => {
    router.delete(destroy.url(confirmingDeleteCategory.value.id), {
        preserveScroll: true,
        onFinish: () => (confirmingDeleteCategory.value = null),
    });
};
</script>

<template>
    <Head :title="$t('ui.categories.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ $t('ui.categories.title') }}
                </h2>
                <PrimaryButton type="button" @click="openCreateModal">
                    <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                    {{ $t('ui.categories.new') }}
                </PrimaryButton>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
            <EmptyState
                v-if="expenseCategories.length === 0 && incomeCategories.length === 0"
                icon="tag"
                :title="$t('ui.categories.empty_title')"
                :description="$t('ui.categories.empty_hint')"
            >
                <template #action>
                    <PrimaryButton type="button" @click="openCreateModal">
                        <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                        {{ $t('ui.categories.create_first') }}
                    </PrimaryButton>
                </template>
            </EmptyState>

            <div v-else class="space-y-8">
                <div v-if="editableCategories.length > 0" class="space-y-2">
                    <label class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400"><BulkCheckbox :bulk="bulk" page /> {{ $t('ui.categories.select_all') }}</label>
                    <BulkDeleteBar :bulk="bulk" :url="bulkDestroy.url()" :noun="$t('ui.categories.noun')" :note="$t('ui.categories.bulk_note')" />
                </div>
                <div v-for="section in [{ label: t('ui.categories.expenses'), items: expenseCategories }, { label: t('ui.categories.income'), items: incomeCategories }]" :key="section.label">
                    <h3 v-if="section.items.length > 0" class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ section.label }}
                    </h3>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="category in section.items"
                            :key="category.id"
                            class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800"
                        >
                            <div class="flex items-center gap-3">
                                <BulkCheckbox v-if="category.editable" :bulk="bulk" :id="category.id" />
                                <CategoryIcon :category="category" size="h-9 w-9" />
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ category.name }}</p>
                                    <span v-if="category.is_system" class="text-xs text-gray-400">{{ $t('ui.categories.system') }}</span>
                                </div>
                            </div>

                            <div v-if="category.editable" class="flex items-center gap-1">
                                <button
                                    type="button"
                                    class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                                    @click="openEditModal(category)"
                                >
                                    <DynamicIcon name="pencil" class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                    @click="confirmDelete(category)"
                                >
                                    <DynamicIcon name="trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ModalForm
            :show="showModal"
            :title="editingCategory ? $t('ui.categories.edit') : $t('ui.categories.new')"
            @close="closeModal"
        >
            <form id="category-form" class="space-y-4" @submit.prevent="submit">
                <div>
                    <InputLabel for="name" :value="$t('ui.categories.name')" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" :placeholder="$t('ui.categories.name_placeholder')" />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel for="type" :value="$t('ui.categories.type')" />
                    <select
                        id="type"
                        v-model="form.type"
                        :disabled="editingCategory !== null"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:disabled:bg-gray-800"
                    >
                        <option value="expense">{{ $t('ui.categories.expense') }}</option>
                        <option value="income">{{ $t('ui.categories.income_one') }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.type" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="icon" :value="$t('ui.categories.icon')" />
                        <select
                            id="icon"
                            v-model="form.icon"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option v-for="choice in iconChoices" :key="choice" :value="choice">{{ choice }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.icon" />
                    </div>
                    <div>
                        <InputLabel for="color" :value="$t('ui.categories.color')" />
                        <input id="color" v-model="form.color" type="color" class="mt-1 block h-10 w-full rounded-lg border-gray-300 dark:border-gray-600" />
                        <InputError class="mt-1" :message="form.errors.color" />
                    </div>
                </div>

                <div class="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-900/40">
                    <span
                        class="inline-flex h-8 w-8 items-center justify-center rounded-full"
                        :style="{ backgroundColor: form.color + '1a', color: form.color }"
                    >
                        <DynamicIcon :name="form.icon" class="h-4 w-4" />
                    </span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $t('ui.categories.preview') }}</span>
                </div>
            </form>

            <template #footer>
                <SecondaryButton type="button" @click="closeModal">{{ $t('ui.common.cancel') }}</SecondaryButton>
                <PrimaryButton form="category-form" type="submit" :disabled="form.processing">{{ $t('ui.categories.save') }}</PrimaryButton>
            </template>
        </ModalForm>

        <Modal :show="confirmingDeleteCategory !== null" @close="confirmingDeleteCategory = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $t('ui.categories.delete_title') }}</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ $t('ui.common.cannot_be_undone') }}
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="confirmingDeleteCategory = null">{{ $t('ui.common.cancel') }}</SecondaryButton>
                    <DangerButton type="button" @click="deleteCategory">{{ $t('ui.common.delete') }}</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
