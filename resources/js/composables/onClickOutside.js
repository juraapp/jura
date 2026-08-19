import { onMounted, onUnmounted } from 'vue';

export function onClickOutside(target, callback) {
    const listener = (event) => {
        const el = target.value;

        if (el && !el.contains(event.target)) {
            callback(event);
        }
    };

    onMounted(() => document.addEventListener('click', listener));
    onUnmounted(() => document.removeEventListener('click', listener));
}
