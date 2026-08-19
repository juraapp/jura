import { reactive } from 'vue';

const toasts = reactive([]);

function remove(id) {
    const index = toasts.findIndex((toast) => toast.id === id);
    if (index !== -1) {
        toasts.splice(index, 1);
    }
}

function push({ type = 'success', message }) {
    const id = Date.now() + Math.random();
    toasts.push({ id, type, message });
    setTimeout(() => remove(id), 4000);
}

export function useToast() {
    return { toasts, push, remove };
}
