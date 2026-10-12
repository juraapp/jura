import { reactive } from 'vue';

const toasts = reactive([]);

function remove(id) {
    const index = toasts.findIndex((toast) => toast.id === id);
    if (index !== -1) {
        toasts.splice(index, 1);
    }
}

/**
 * Shows a toast. An `action` ({ label, onClick }) adds a button (for example Undo)
 * and keeps the toast around longer.
 */
function push({ type = 'success', message, action = null }) {
    const id = Date.now() + Math.random();
    toasts.push({ id, type, message, action });
    setTimeout(() => remove(id), action ? 10000 : 4000);
}

export function useToast() {
    return { toasts, push, remove };
}
