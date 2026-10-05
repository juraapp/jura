import { ref, watch } from 'vue';

const isDark = ref(document.documentElement.classList.contains('dark'));

watch(isDark, (value) => {
    localStorage.setItem('dark-mode', value);
    document.documentElement.classList.toggle('dark', value);
    window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark: value } }));
});

export function useDarkMode() {
    const toggle = () => {
        isDark.value = !isDark.value;
    };

    return { isDark, toggle };
}
