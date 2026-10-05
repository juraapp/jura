# Dark Mode

## FOUC prevention

`resources/views/app.blade.php` (the Inertia root view) runs a synchronous inline `<script>` in `<head>`, before Vue mounts:

```html
<script>
    if (localStorage.getItem('dark-mode') === 'true' ||
        (localStorage.getItem('dark-mode') === null && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    }
</script>
```

This has to run before first paint and before any framework boots — it's plain JS with no Alpine/Vue dependency, ported as-is from the old Blade layout. Without it, every page load would flash light mode for a frame before Vue applies the saved preference.

## `useDarkMode()`

`resources/js/composables/useDarkMode.js` — a module-scope `ref` (same shared-singleton pattern as `useTransactionModal`, see `frontend-conventions.md`), initialized by reading the `dark` class the FOUC script already applied:

```js
const isDark = ref(document.documentElement.classList.contains('dark'));

watch(isDark, (value) => {
    localStorage.setItem('dark-mode', value);
    document.documentElement.classList.toggle('dark', value);
    window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark: value } }));
});

export function useDarkMode() {
    return { isDark, toggle: () => (isDark.value = !isDark.value) };
}
```

`Topbar.vue`'s theme button calls `toggle()`; any component can read `isDark` reactively via the same composable.

## Chart re-theming

Charts don't listen for the `theme-changed` event — `vue3-apexcharts` re-renders a chart from scratch when its Vue component remounts, so `Dashboard.vue` forces a remount on theme change by keying each `<apexchart>` on `isDark`:

```vue
<apexchart :key="`category-${currency.currency}-${isDark}`" :options="..." :series="..." />
```

`chartPalette.js` exports a `CHART_PALETTE` with separate `light`/`dark` entries; the chart-building helper picks `CHART_PALETTE[isDark.value ? 'dark' : 'light']` each time it's called, so the remount picks up the correct palette automatically. If you add a new chart, follow this `:key` pattern rather than trying to imperatively update an existing chart instance's options on theme change — simpler, and consistent with how every other chart on the dashboard handles it.
