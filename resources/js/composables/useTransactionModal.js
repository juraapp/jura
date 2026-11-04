import { ref } from 'vue';

const isOpen = ref(false);
/** 'create' | 'editTransaction' | 'editPlanned' | 'editSeries' | 'confirmPlanned' */
const intent = ref('create');
const editingTransaction = ref(null);
const plannedEvent = ref(null);
const seriesId = ref(null);
const defaultType = ref('expense');
const defaultWhen = ref('happened');
/** Bumped on every open, so the form inside the modal starts fresh. */
const openCount = ref(0);

const show = (values) => {
    intent.value = values.intent;
    editingTransaction.value = values.transaction ?? null;
    plannedEvent.value = values.event ?? null;
    seriesId.value = values.seriesId ?? null;
    defaultType.value = values.type ?? 'expense';
    defaultWhen.value = values.when ?? 'happened';
    openCount.value++;
    isOpen.value = true;
};

/**
 * The one money-movement form, mounted once in AuthenticatedLayout. Module-scope refs make it a shared
 * singleton any page or component can drive.
 */
export function useTransactionModal() {
    /** Create (null) or edit (a transaction id) a real transaction; `when: 'planned'` starts a new plan instead. */
    const open = (transaction = null, type = 'expense', when = 'happened') => {
        show({ intent: transaction ? 'editTransaction' : 'create', transaction, type, when });
    };

    /** Create a plan (null) or edit one (a presented planned event). */
    const openPlanned = (event = null, { type = 'expense' } = {}) => {
        show(event ? { intent: 'editPlanned', event, type: event.type } : { intent: 'create', type, when: 'planned' });
    };

    /** Edit a whole repeating plan from today on. */
    const openSeries = (id) => {
        show({ intent: 'editSeries', seriesId: id });
    };

    /** Record a plan as a real transaction, prefilled from the plan. */
    const openConfirm = (event) => {
        show({ intent: 'confirmPlanned', event, type: event.type });
    };

    const close = () => {
        isOpen.value = false;
        editingTransaction.value = null;
        plannedEvent.value = null;
        seriesId.value = null;
    };

    return {
        isOpen,
        intent,
        editingTransaction,
        plannedEvent,
        seriesId,
        defaultType,
        defaultWhen,
        openCount,
        open,
        openPlanned,
        openSeries,
        openConfirm,
        close,
    };
}
