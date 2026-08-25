<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { withCsrfHeader } from '@/csrf';
import { parse as parseImport, store as storeImport } from '@/actions/App/Http/Controllers/TransactionImportController';
import { index as transactionsIndex } from '@/routes/transactions';
import { template as importTemplate } from '@/routes/transactions/import';

const step = ref('idle');
const dragging = ref(false);
const processing = ref(false);
const parseError = ref(null);
const fileInput = ref(null);
const rows = ref([]);
const includeDuplicates = ref(false);
const result = ref({ imported: 0, skipped: 0, duplicateSkipped: 0 });

const validCount = computed(() => rows.value.filter((row) => row.status === 'valid').length);
const duplicateCount = computed(() => rows.value.filter((row) => row.status === 'duplicate').length);
const errorCount = computed(() => rows.value.filter((row) => row.status === 'error').length);

const uploadFile = async (file) => {
    parseError.value = null;
    processing.value = true;

    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await fetch(parseImport.url(), {
            method: 'POST',
            headers: withCsrfHeader({ Accept: 'application/json' }),
            body: formData,
        });

        const data = await response.json();

        if (!response.ok) {
            parseError.value = data.error ?? 'Something went wrong while parsing the file.';
            return;
        }

        rows.value = data.rows;
        step.value = 'preview';
    } catch {
        parseError.value = 'Something went wrong while parsing the file.';
    } finally {
        processing.value = false;
        if (fileInput.value) {
            fileInput.value.value = '';
        }
    }
};

const onFileSelected = (event) => {
    const file = event.target.files[0];
    if (file) {
        uploadFile(file);
    }
};

const onDrop = (event) => {
    dragging.value = false;
    const file = event.dataTransfer.files[0];
    if (file) {
        uploadFile(file);
    }
};

const confirmImport = async () => {
    processing.value = true;

    try {
        const response = await fetch(storeImport.url(), {
            method: 'POST',
            headers: withCsrfHeader({ Accept: 'application/json', 'Content-Type': 'application/json' }),
            body: JSON.stringify({
                rows: rows.value.map((row) => row.raw),
                include_duplicates: includeDuplicates.value,
            }),
        });

        result.value = await response.json();
        step.value = 'done';
    } finally {
        processing.value = false;
    }
};

const startOver = () => {
    step.value = 'idle';
    rows.value = [];
    includeDuplicates.value = false;
    parseError.value = null;
    result.value = { imported: 0, skipped: 0, duplicateSkipped: 0 };
};

const doneDescription = computed(() => {
    const parts = [];
    if (result.value.skipped > 0) {
        parts.push(`${result.value.skipped} row(s) were skipped due to errors.`);
    }
    if (result.value.duplicateSkipped > 0) {
        parts.push(`${result.value.duplicateSkipped} possible duplicate(s) were not imported.`);
    }
    return parts.join(' ') || null;
});
</script>

<template>
    <Head title="Import transactions" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Import transactions
                </h2>
                <Link :href="transactionsIndex.url()" class="text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    Back to transactions
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <div
                v-if="step === 'idle'"
                class="rounded-xl border-2 border-dashed px-6 py-16 text-center transition"
                :class="dragging ? 'border-primary-400 bg-primary-50 dark:bg-primary-500/10' : 'border-gray-300 dark:border-gray-700'"
                @dragover.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop.prevent="onDrop"
            >
                <input ref="fileInput" type="file" accept=".csv,text/csv" class="hidden" @change="onFileSelected" />

                <DynamicIcon name="arrow-up-tray" class="mx-auto h-10 w-10 text-gray-400" />

                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    Drag your CSV file here or
                    <button type="button" class="font-medium text-primary-600 hover:underline" @click="fileInput.click()">
                        choose a file
                    </button>
                </p>

                <div v-if="processing" class="mt-3 text-sm text-gray-500">Processing file…</div>

                <InputError class="mt-3" :message="parseError" />

                <div class="mt-6 space-y-2">
                    <a :href="importTemplate.url()" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:underline">
                        <DynamicIcon name="arrow-down-tray" class="h-4 w-4" />
                        Download CSV template
                    </a>
                    <p class="text-xs text-gray-400">
                        Replace the sample rows with your real data, using the exact names of your existing accounts and categories.
                    </p>
                </div>
            </div>

            <template v-else-if="step === 'preview'">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            <span class="font-semibold text-green-600 dark:text-green-400">{{ validCount }} new</span>
                            &middot;
                            <span class="font-semibold text-amber-600 dark:text-amber-400">{{ duplicateCount }} possible duplicates</span>
                            &middot;
                            <span class="font-semibold text-red-600 dark:text-red-400">{{ errorCount }} with errors</span>
                        </p>

                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <input v-model="includeDuplicates" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600" />
                                Also import duplicates
                            </label>
                            <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700" @click="startOver">
                                Cancel
                            </button>
                            <PrimaryButton type="button" :disabled="processing || (validCount === 0 && !includeDuplicates)" @click="confirmImport">
                                Import
                            </PrimaryButton>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/40">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Line</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Category</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Account</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="row in rows" :key="row.line">
                                    <td class="px-4 py-2.5 text-sm text-gray-400">{{ row.line }}</td>
                                    <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300">{{ row.raw.date }}</td>
                                    <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300">{{ row.raw.type }}</td>
                                    <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300">{{ row.raw.category }}</td>
                                    <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300">{{ row.raw.account }}</td>
                                    <td class="px-4 py-2.5 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300">{{ row.raw.amount }}</td>
                                    <td class="px-4 py-2.5 text-sm">
                                        <span v-if="row.status === 'valid'" class="inline-flex items-center gap-1 text-green-600 dark:text-green-400">
                                            <DynamicIcon name="check-circle" class="h-4 w-4" />
                                            New
                                        </span>
                                        <span
                                            v-else-if="row.status === 'duplicate'"
                                            class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400"
                                            title="A transaction with the same account, date, amount, and description already exists."
                                        >
                                            <DynamicIcon name="exclamation-triangle" class="h-4 w-4" />
                                            Possible duplicate
                                        </span>
                                        <span v-else class="inline-flex items-start gap-1 text-red-600 dark:text-red-400">
                                            <DynamicIcon name="exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                                            <span>{{ row.errors.join(' ') }}</span>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <EmptyState
                v-else
                icon="check-circle"
                :title="`${result.imported} transaction(s) imported.`"
                :description="doneDescription"
            >
                <template #action>
                    <div class="flex items-center gap-3">
                        <PrimaryButton type="button" @click="startOver">
                            <DynamicIcon name="arrow-up-tray" class="mr-1.5 h-4 w-4" />
                            Import another file
                        </PrimaryButton>
                        <Link :href="transactionsIndex.url()" class="text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                            View transactions
                        </Link>
                    </div>
                </template>
            </EmptyState>
        </div>
    </AuthenticatedLayout>
</template>
