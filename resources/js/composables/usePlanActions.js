import { router } from '@inertiajs/vue3';
import { useTransactionModal } from '@/composables/useTransactionModal';
import { realize, skip, link } from '@/actions/App/Modules/Planning/Http/Controllers/PlannedEventController';

/**
 * What a planned row can do wherever it is listed (cash flow, the transactions bands, the review inbox).
 */
export function usePlanActions() {
    const { openPlanned, openConfirm } = useTransactionModal();

    /** One click when the plan already says where the money goes; otherwise the form asks for what is missing. */
    const confirm = (event) => {
        if (event.account && event.category) {
            router.post(realize.url(event.id), {}, { preserveScroll: true });

            return;
        }

        openConfirm(event);
    };

    return {
        confirm,
        /** Confirm with a different amount, date or account. */
        adjust: (event) => openConfirm(event),
        skip: (event) => router.post(skip.url(event.id), {}, { preserveScroll: true }),
        match: (event) => router.post(link.url(event.id), { transaction_id: event.suggestedMatch.id }, { preserveScroll: true }),
        edit: (event) => openPlanned(event),
    };
}
