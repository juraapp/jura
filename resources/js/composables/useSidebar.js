import { ref } from 'vue';

const isOpen = ref(false);

export function useSidebar() {
    const toggle = () => {
        isOpen.value = !isOpen.value;
    };

    const close = () => {
        isOpen.value = false;
    };

    return { isOpen, toggle, close };
}
