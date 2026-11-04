<script setup>
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ModalForm from '@/Components/ModalForm.vue';
import MoneyMovementForm from '@/Components/MoneyMovementForm.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useTransactionModal } from '@/composables/useTransactionModal';
import { useToast } from '@/composables/useToast';
import { formOptions } from '@/actions/App/Modules/Ledger/Http/Controllers/TransactionController';
import { formOptions as plannedFormOptions } from '@/actions/App/Modules/Planning/Http/Controllers/PlannedEventController';

/**
 * The modal around the one money-movement form. It loads the account and category lists once, and the
 * plan options (frequencies, certainties, buckets) the first time a plan is shown.
 */
const { t } = useI18n();
const { isOpen, intent, editingTransaction, plannedEvent, seriesId, defaultType, defaultWhen, openCount, openSeries, close } = useTransactionModal();
const { push: pushToast } = useToast();

const options = reactive({ accounts: [], expenseCategories: [], incomeCategories: [], planned: null });
const ready = ref(false);
const busy = ref(false);
const title = ref('');
const formRef = ref(null);

const fetchJson = async (url) => {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
};

let baseOptions = null;
const loadOptions = async () => {
    baseOptions ??= fetchJson(formOptions.url()).then((data) => {
        options.accounts = data.accounts;
        options.expenseCategories = data.expenseCategories;
        options.incomeCategories = data.incomeCategories;
    }).catch((error) => {
        baseOptions = null;
        throw error;
    });

    return baseOptions;
};

let plannedOptions = null;
const loadPlannedOptions = () => {
    plannedOptions ??= fetchJson(plannedFormOptions.url()).then((data) => {
        options.planned = data;
    }).catch((error) => {
        plannedOptions = null;
        throw error;
    });

    return plannedOptions;
};

const failed = () => {
    pushToast({ type: 'error', message: t('ui.common.request_failed') });
    close();
};

const prepare = async () => {
    ready.value = false;
    try {
        await loadOptions();
        ready.value = true;
    } catch {
        failed();
    }
};

watch(openCount, prepare);
</script>

<template>
    <ModalForm :show="isOpen" :title="title || $t('ui.transaction_modal.new')" @close="close">
        <MoneyMovementForm
            v-if="isOpen && ready"
            ref="formRef"
            :key="openCount"
            :intent="intent"
            :transaction-id="editingTransaction"
            :event="plannedEvent"
            :series-id="seriesId"
            :default-type="defaultType"
            :default-when="defaultWhen"
            :options="options"
            :load-planned-options="loadPlannedOptions"
            @saved="close"
            @failed="failed"
            @busy="busy = $event"
            @title="title = $event"
            @edit-series="openSeries"
        />
        <div v-else class="py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ $t('ui.common.loading') }}</div>

        <template #footer>
            <SecondaryButton type="button" @click="close">{{ $t('ui.common.cancel') }}</SecondaryButton>
            <PrimaryButton type="button" :disabled="busy || !ready" @click="formRef?.submit(false)">
                {{ intent === 'confirmPlanned' ? $t('ui.transaction_modal.record') : $t('ui.transaction_modal.save') }}
            </PrimaryButton>
        </template>
    </ModalForm>
</template>
