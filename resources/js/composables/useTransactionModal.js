import { ref } from 'vue';

const isOpen = ref(false);
const editingTransaction = ref(null);
const defaultType = ref('expense');

export function useTransactionModal() {
    const open = (transaction = null, type = 'expense') => {
        editingTransaction.value = transaction;
        defaultType.value = type;
        isOpen.value = true;
    };

    const close = () => {
        isOpen.value = false;
        editingTransaction.value = null;
    };

    return { isOpen, editingTransaction, defaultType, open, close };
}
