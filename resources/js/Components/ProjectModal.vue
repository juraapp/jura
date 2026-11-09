<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import CurrencySelect from '@/Components/CurrencySelect.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import ModalForm from '@/Components/ModalForm.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import {
    store as storeProject,
    update as updateProject,
} from '@/actions/App/Modules/Planning/Http/Controllers/ProjectController';

/**
 * Creates or edits a project: name, type, colour, notes, one currency, an optional budget and target date, and an
 * optional savings bucket (one of the user's that no other project uses, or a new one made for the project).
 * Used by the Projects pages and the Wish list.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    /** A project entry from ProjectOverview, or null to create one. */
    project: { type: Object, default: null },
    kinds: { type: Array, required: true },
    /** The currencies of the user's accounts. */
    currencies: { type: Array, required: true },
    /** The user's savings buckets: { id, name, currency }. */
    buckets: { type: Array, required: true },
    /** Every project, to tell which buckets another project already uses. */
    projects: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const blank = () => ({
    name: '',
    kind: 'project',
    color: '#2563eb',
    notes: '',
    currency: props.currencies[0] ?? '',
    budget_amount: '',
    target_date: '',
    bucket_mode: 'none',
    bucket_id: '',
    new_bucket: { fill_rule: 'fixed', fill_value: '' },
});

const form = useForm(blank());

watch(() => props.show, (open) => {
    if (!open) return;

    form.clearErrors();
    form.defaults(blank());
    form.reset();

    const project = props.project;
    if (project) {
        form.name = project.name ?? '';
        form.kind = project.kind ?? 'project';
        form.color = project.color ?? '#2563eb';
        form.notes = project.notes ?? '';
        form.currency = project.currency ?? props.currencies[0] ?? '';
        form.budget_amount = project.budget !== null && project.budget !== undefined ? String(project.budget) : '';
        form.target_date = project.targetDate ?? '';
        form.bucket_mode = project.bucket ? 'existing' : 'none';
        form.bucket_id = project.bucket?.id ?? '';
    }
});

/** The savings buckets this project can use: in its currency and not used by another project. */
const freeBuckets = computed(() => {
    const taken = new Set(props.projects.filter((other) => other.id !== props.project?.id && other.bucket).map((other) => other.bucket.id));

    return props.buckets.filter((bucket) => bucket.currency === form.currency && !taken.has(bucket.id));
});

watch(() => form.currency, () => {
    if (form.bucket_mode === 'existing' && !freeBuckets.value.some((bucket) => bucket.id === Number(form.bucket_id))) {
        form.bucket_id = '';
    }
});

const percentFill = computed(() => form.new_bucket.fill_rule !== 'fixed');

const submit = () => {
    const action = props.project ? updateProject(props.project.id) : storeProject();

    form.transform((data) => ({
        ...data,
        budget_amount: data.budget_amount === '' ? null : data.budget_amount,
        target_date: data.target_date === '' ? null : data.target_date,
        bucket_id: data.bucket_mode === 'existing' && data.bucket_id !== '' ? data.bucket_id : null,
        new_bucket: data.bucket_mode === 'new' ? data.new_bucket : null,
    })).submit(action, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <ModalForm :show="show" :title="project ? $t('ui.projects.edit_title') : $t('ui.projects.new_title')" @close="emit('close')">
        <form id="project-form" class="space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel for="project_name" :value="$t('ui.projects.name')" />
                <TextInput id="project_name" v-model="form.name" type="text" class="mt-1 block w-full" :placeholder="$t('ui.projects.name_placeholder')" />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="project_kind" :value="$t('ui.projects.kind')" />
                    <SelectInput id="project_kind" v-model="form.kind">
                        <option v-for="kind in kinds" :key="kind.value" :value="kind.value">{{ kind.label }}</option>
                    </SelectInput>
                    <InputError class="mt-1" :message="form.errors.kind" />
                </div>
                <div>
                    <InputLabel for="project_color" :value="$t('ui.projects.colour')" />
                    <input id="project_color" v-model="form.color" type="color" class="mt-1 h-10 w-full rounded-lg border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-700" />
                    <InputError class="mt-1" :message="form.errors.color" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <InputLabel for="project_currency" :value="$t('ui.projects.currency')" />
                    <CurrencySelect id="project_currency" v-model="form.currency" :account-currencies="currencies" />
                    <InputError class="mt-1" :message="form.errors.currency" />
                </div>
                <div>
                    <InputLabel for="project_budget" :value="$t('ui.projects.budget')" />
                    <MoneyInput id="project_budget" v-model="form.budget_amount" :currency="form.currency" class="mt-1" />
                    <InputError class="mt-1" :message="form.errors.budget_amount" />
                </div>
                <div>
                    <InputLabel for="project_target" :value="$t('ui.projects.target_date')" />
                    <TextInput id="project_target" v-model="form.target_date" type="date" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.target_date" />
                </div>
            </div>

            <fieldset class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                <legend class="px-1 text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('ui.projects.bucket') }}</legend>
                <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.projects.bucket_hint') }}</p>

                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input v-model="form.bucket_mode" type="radio" value="none" class="text-primary-600 focus:ring-primary-500" />
                        {{ $t('ui.projects.bucket_none') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input v-model="form.bucket_mode" type="radio" value="existing" class="text-primary-600 focus:ring-primary-500" />
                        {{ $t('ui.projects.bucket_existing') }}
                    </label>
                    <div v-if="form.bucket_mode === 'existing'" class="pl-6">
                        <SelectInput id="project_bucket" v-model="form.bucket_id">
                            <option value="">{{ $t('ui.projects.bucket_pick') }}</option>
                            <option v-for="bucket in freeBuckets" :key="bucket.id" :value="bucket.id">{{ bucket.name }}</option>
                        </SelectInput>
                        <p v-if="freeBuckets.length === 0" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.projects.bucket_none_available') }}</p>
                        <InputError class="mt-1" :message="form.errors.bucket_id" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input v-model="form.bucket_mode" type="radio" value="new" class="text-primary-600 focus:ring-primary-500" />
                        {{ $t('ui.projects.bucket_new') }}
                    </label>
                    <div v-if="form.bucket_mode === 'new'" class="space-y-2 pl-6">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ui.projects.new_bucket_target') }}</p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <InputLabel for="new_bucket_rule" :value="$t('ui.projects.new_bucket_fill')" />
                                <SelectInput id="new_bucket_rule" v-model="form.new_bucket.fill_rule">
                                    <option value="fixed">{{ $t('ui.projects.fill_fixed') }}</option>
                                    <option value="percent">{{ $t('ui.projects.fill_percent') }}</option>
                                    <option value="of_remaining">{{ $t('ui.projects.fill_of_remaining') }}</option>
                                </SelectInput>
                                <InputError class="mt-1" :message="form.errors['new_bucket.fill_rule']" />
                            </div>
                            <div>
                                <InputLabel for="new_bucket_value" :value="percentFill ? $t('ui.projects.fill_percentage') : $t('ui.projects.fill_amount')" />
                                <MoneyInput v-if="!percentFill" id="new_bucket_value" v-model="form.new_bucket.fill_value" :currency="form.currency" class="mt-1" />
                                <TextInput v-else id="new_bucket_value" v-model="form.new_bucket.fill_value" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" />
                                <InputError class="mt-1" :message="form.errors['new_bucket.fill_value']" />
                            </div>
                        </div>
                    </div>
                </div>
            </fieldset>

            <div>
                <InputLabel for="project_notes" :value="$t('ui.projects.notes')" />
                <TextInput id="project_notes" v-model="form.notes" type="text" class="mt-1 block w-full" />
                <InputError class="mt-1" :message="form.errors.notes" />
            </div>
        </form>

        <template #footer>
            <SecondaryButton type="button" @click="emit('close')">{{ $t('ui.common.cancel') }}</SecondaryButton>
            <PrimaryButton form="project-form" type="submit" :disabled="form.processing">{{ $t('ui.projects.save') }}</PrimaryButton>
        </template>
    </ModalForm>
</template>
