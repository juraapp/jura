import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { createFormat } from '@/format';

/**
 * Formatters bound to the signed-in user's display preferences. Reactive: they
 * pick up a preference change as soon as the page props refresh.
 */
export function useFormat() {
    const page = usePage();
    const current = computed(() => createFormat(page.props.auth?.user, page.props.auth?.user?.locale ?? 'en'));

    return {
        number: (...args) => current.value.number(...args),
        date: (...args) => current.value.date(...args),
        month: (...args) => current.value.month(...args),
        time: (...args) => current.value.time(...args),
        dateTime: (...args) => current.value.dateTime(...args),
        money: (...args) => current.value.money(...args),
    };
}
