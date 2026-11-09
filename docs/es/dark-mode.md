# Modo oscuro

## Prevención de FOUC

`resources/views/app.blade.php` (la vista raíz de Inertia) ejecuta un `<script>` en línea y síncrono en el `<head>`, antes de que Vue se monte:

```html
<script>
    if (localStorage.getItem('dark-mode') === 'true' ||
        (localStorage.getItem('dark-mode') === null && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    }
</script>
```

Esto tiene que ejecutarse antes del primer pintado y antes de que arranque cualquier framework: es JS plano sin dependencia de Alpine/Vue, portado tal cual desde el antiguo layout Blade. Sin él, cada carga de página mostraría un destello del modo claro durante un frame antes de que Vue aplique la preferencia guardada.

## `useDarkMode()`

`resources/js/composables/useDarkMode.js` — un `ref` a nivel de módulo (el mismo patrón de singleton compartido que `useTransactionModal`, ver `frontend-conventions.md`), inicializado leyendo la clase `dark` que el script anti-FOUC ya aplicó:

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

El botón de tema de `Topbar.vue` llama a `toggle()`; cualquier componente puede leer `isDark` de forma reactiva mediante el mismo composable.

## Re-tematizado de gráficos

Los gráficos no escuchan el evento `theme-changed`: `vue3-apexcharts` vuelve a renderizar un gráfico desde cero cuando su componente Vue se vuelve a montar, así que `Dashboard.vue` fuerza un remontaje al cambiar el tema asignando a cada `<apexchart>` un `:key` basado en `isDark`:

```vue
<apexchart :key="`category-${currency.currency}-${isDark}`" :options="..." :series="..." />
```

`chartPalette.js` exporta un `CHART_PALETTE` con entradas `light`/`dark` separadas; el helper que construye los gráficos elige `CHART_PALETTE[isDark.value ? 'dark' : 'light']` cada vez que se llama, de modo que el remontaje toma la paleta correcta automáticamente. Si agregas un gráfico nuevo, sigue este patrón de `:key` en lugar de intentar actualizar de forma imperativa las opciones de una instancia de gráfico existente al cambiar el tema: es más simple y consistente con cómo lo manejan todos los demás gráficos del dashboard.
