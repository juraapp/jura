import { computed, ref } from 'vue';

/**
 * Multi-select state for a list. Pass a getter for the rows currently on screen.
 * `allMatching` means "everything the filters match", beyond the visible page.
 */
export function useBulkSelection(rows) {
    const selected = ref(new Set());
    const allMatching = ref(false);

    const ids = computed(() => [...selected.value]);
    const count = computed(() => selected.value.size);
    const pageIds = computed(() => rows().map((row) => row.id));
    const allOnPageSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every((id) => selected.value.has(id)));
    const someSelected = computed(() => selected.value.size > 0);

    const isSelected = (id) => selected.value.has(id);

    const toggle = (id) => {
        const next = new Set(selected.value);
        next.has(id) ? next.delete(id) : next.add(id);
        selected.value = next;
        allMatching.value = false;
    };

    const togglePage = () => {
        selected.value = allOnPageSelected.value ? new Set() : new Set(pageIds.value);
        allMatching.value = false;
    };

    const clear = () => {
        selected.value = new Set();
        allMatching.value = false;
    };

    return { ids, count, isSelected, toggle, togglePage, clear, allOnPageSelected, someSelected, allMatching };
}
