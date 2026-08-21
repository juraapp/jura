<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
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
import { store, update, destroy } from '@/actions/App/Http/Controllers/CategoryController';

defineProps({
    expenseCategories: { type: Array, required: true },
    incomeCategories: { type: Array, required: true },
    iconChoices: { type: Array, required: true },
});

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
    <Head title="Categories" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Categories
                </h2>
                <PrimaryButton type="button" @click="openCreateModal">
                    <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                    New category
                </PrimaryButton>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
            <EmptyState
                v-if="expenseCategories.length === 0 && incomeCategories.length === 0"
                icon="tag"
                title="You don't have any categories yet"
                description="Create categories to organize your income and expenses."
            >
                <template #action>
                    <PrimaryButton type="button" @click="openCreateModal">
                        <DynamicIcon name="plus" class="mr-1.5 h-4 w-4" />
                        Create my first category
                    </PrimaryButton>
                </template>
            </EmptyState>

            <div v-else class="space-y-8">
                <div v-for="section in [{ label: 'Expenses', items: expenseCategories }, { label: 'Income', items: incomeCategories }]" :key="section.label">
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
                                <CategoryIcon :category="category" size="h-9 w-9" />
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ category.name }}</p>
                                    <span v-if="category.is_system" class="text-xs text-gray-400">System category</span>
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
            :title="editingCategory ? 'Edit category' : 'New category'"
            @close="closeModal"
        >
            <form id="category-form" class="space-y-4" @submit.prevent="submit">
                <div>
                    <InputLabel for="name" value="Name" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" placeholder="Groceries" />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel for="type" value="Type" />
                    <select
                        id="type"
                        v-model="form.type"
                        :disabled="editingCategory !== null"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:disabled:bg-gray-800"
                    >
                        <option value="expense">Expense</option>
                        <option value="income">Income</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.type" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="icon" value="Icon" />
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
                        <InputLabel for="color" value="Color" />
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
                    <span class="text-sm text-gray-500 dark:text-gray-400">Preview</span>
                </div>
            </form>

            <template #footer>
                <SecondaryButton type="button" @click="closeModal">Cancel</SecondaryButton>
                <PrimaryButton form="category-form" type="submit" :disabled="form.processing">Save</PrimaryButton>
            </template>
        </ModalForm>

        <Modal :show="confirmingDeleteCategory !== null" @close="confirmingDeleteCategory = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delete this category?</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    This action cannot be undone.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="confirmingDeleteCategory = null">Cancel</SecondaryButton>
                    <DangerButton type="button" @click="deleteCategory">Delete</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
