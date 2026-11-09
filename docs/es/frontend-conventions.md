# Convenciones de frontend

## Listas con acciones masivas
Usa `useBulkSelection(() => rows)` con `<BulkCheckbox>` por fila (y `page` en el encabezado) y `<BulkDeleteBar>` para el
simple borrado múltiple; agrega una acción `bulkDestroy` en el controlador que reciba `ids[]` (`Shared\Http\Requests\BulkIdsRequest`, máx. 500).
Los mensajes flash del servidor (`success`/`error`/`warning`, más `undo: {url, ids}`) se muestran como toasts mediante `ToastContainer`.

## Formato de fechas, horas, números y dinero
Nunca llames a `toLocaleDateString`, `toFixed` ni `Intl.NumberFormat` para valores visibles al usuario. Usa `useFormat()`
(`resources/js/composables/useFormat.js`: `date`, `time`, `dateTime`, `number`, `money`) para que las
Preferencias del usuario (estilo de fecha, 12/24 h, separadores, decimales, visualización de la moneda) se apliquen en todas partes. `<Money>` y los
helpers de gráficas en `chartPalette.js` ya lo hacen. Las reglas viven en `resources/js/format.js` y se replican para el
servidor en `App\Modules\Shared\Support\UserFormat` (PDFs, notificaciones); cambia ambos a la vez. `format.js` no tiene
imports de ningún framework y se prueba con `npm run test:js` (`node --test`).

## Pages vs Components vs Layouts

- **`Pages/`** — un archivo por cada destino de `Inertia::render('X')`, con la misma ruta (`Pages/Accounts/Index.vue` ↔ `Inertia::render('Accounts/Index', ...)`). Una Page siempre envuelve su contenido en `<AuthenticatedLayout>` (o `<GuestLayout>` para las pantallas de autenticación) y recibe sus datos exclusivamente mediante `defineProps()`; nunca obtiene por sí misma sus datos iniciales.
- **`Components/`** — todo lo que se reutiliza en ≥2 Pages, o una primitiva de UI autocontenida (`Modal`, `ModalForm`, `InputLabel`, `TextInput`, `InputError`, `PrimaryButton`/`SecondaryButton`/`DangerButton`, `EmptyState`, `StatCard`, `Money`, `CategoryIcon`, `DynamicIcon`, `Pagination`, `SelectInput`, `SegmentedControl`). Revisa aquí antes de escribir uno nuevo: la mayoría de las páginas CRUD se construyen casi por completo con componentes existentes (mira cualquier `Pages/*/Index.vue` para ver el patrón: `ModalForm` para crear/editar, un `Modal` simple para confirmar el borrado, `EmptyState` para el caso de cero filas).
- **`Layouts/`** — `AuthenticatedLayout.vue` (barra lateral + barra superior + contenedor de toasts + el modal global de transacciones) y `GuestLayout.vue`. Una Page elige uno; los layouts nunca conocen los datos de una Page específica.

## La estructura establecida de las páginas CRUD

Cada `Index.vue` de dominio (Accounts, Categories, Budgets, SavingsGoals, RecurringTransactions) sigue la misma estructura: copia la más parecida existente en lugar de inventar una nueva:

```vue
<script setup>
const form = useForm({ ...defaults });
const showModal = ref(false);
const editingX = ref(null);       // null = crear, con valor = editar
const confirmingDeleteX = ref(null);

const openCreateModal = () => { editingX.value = null; form.reset(); form.clearErrors(); showModal.value = true; };
const openEditModal = (x) => { editingX.value = x; form.clearErrors(); /* poblar form.* desde x */ showModal.value = true; };
const submit = () => {
    const action = editingX.value ? update(editingX.value.id) : store();
    form.submit(action, { preserveScroll: true, onSuccess: () => (showModal.value = false) });
};
</script>
```

- **Un `<select>`/`<input>` deshabilitado igualmente envía su valor.** El `useForm()` de Inertia serializa directamente el objeto reactivo de JS del formulario (no un `FormData` nativo recolectado desde el DOM), así que `:disabled="editingBudget !== null"` en un campo solo impide que el *usuario* lo cambie: el valor se envía de todos modos. Esto replica el comportamiento original de Livewire (una propiedad enlazada con `wire:model` se mantiene en el estado del servidor sin importar `@disabled` en el elemento renderizado) y se usa deliberadamente en `Budgets/Index.vue` para bloquear visualmente `category_id`/`currency`/`period_type` al editar, devolviendo aun así los valores existentes.
- La confirmación de borrado es un `Modal` simple, no un `ModalForm` (sin formulario dentro, solo un sí/no).
- Los mensajes flash (`back()->with('success', ...)`) **no** están conectados actualmente al `ToastContainer`: `useToast()` existe y `flash` es una prop compartida, pero ninguna página lee `page.props.flash` para mostrar un toast todavía. Es una brecha conocida, no una página que copiaste mal.

## El modal global de transacciones

`Components/GlobalTransactionModal.vue` se monta una sola vez en `AuthenticatedLayout.vue` para que cualquier página pueda abrirlo: el botón "Nueva transacción" de la barra superior, un CTA de estado vacío o la acción de editar de una fila de transacción llaman al mismo composable:

```js
import { useTransactionModal } from '@/composables/useTransactionModal';
const { open } = useTransactionModal();

open(null, 'expense');   // crear, pestaña por defecto = gasto
open(transaction.id);    // editar: pasa el *id* de la transacción, no un objeto hidratado
```

`useTransactionModal.js` mantiene su estado en **`ref`s de ámbito de módulo** (un patrón simple de singleton compartido), no en `provide`/`inject` de Vue: es más simple que el borrador de provide/inject del plan original y igual de global, ya que los módulos ES son singletons. Si necesitas el mismo patrón de "cualquier componente puede manejar un estado compartido" en otro lugar, copia la forma de este composable en lugar de recurrir a `provide`/`inject` o a una librería de estado.

El modal obtiene sus propios datos de referencia **al abrirse**, mediante `fetch()` simple contra dos pequeños endpoints JSON que no son de Inertia (`TransactionController@formOptions`, `@editData`) en lugar de props compartidas de Inertia; es deliberado, para que cada página no tenga que cargar `accounts`/`categories` por si el modal se abre. Estas llamadas `fetch()` necesitan un encabezado CSRF manual (`withCsrfHeader()` de `resources/js/csrf.js`, que lee la cookie `XSRF-TOKEN`), ya que omiten el router de Inertia, que maneja CSRF automáticamente. El asistente de importación CSV (`Pages/Transactions/Import.vue`) usa el mismo patrón `fetch()` + `withCsrfHeader()` contra sus propios endpoints JSON `parse`/`store`, por la misma razón (estado de un asistente de varios pasos que no corresponde a una única visita de página de Inertia).

## Navegación de Flowbite + Inertia

Los dropdowns/modales/tooltips de Flowbite se conectan mediante atributos `data-*` y solo se inicializan automáticamente en `DOMContentLoaded`. Como Inertia intercambia la página sin una recarga completa, `resources/js/app.js` vuelve a ejecutar el inicializador después de cada navegación del lado del cliente:

```js
router.on('navigate', () => initFlowbite());
```

Para cualquier cosa renderizada desde un `v-for` (un dropdown por fila, etc.), prefiere un `ref` + `v-show`/`v-if` nativos de Vue en lugar de confiar en que el re-escaneo del DOM de Flowbite encuentre los elementos agregados dinámicamente.

## Helpers de acciones de Wayfinder

Los helpers de acciones se generan a partir del nombre completo de la clase de cada controlador, así que la ruta de importación incluye su módulo (`@/actions/App/Modules/<Module>/Http/Controllers/<Name>Controller`). Mover o renombrar un controlador implica regenerar (`vendor/bin/sail artisan wayfinder:generate --with-form`) y actualizar los imports; `npm run build` falla de forma evidente si alguno está desactualizado. Nunca escribas una URL a mano. Importa el helper generado y pásalo a `useForm().submit()` / `router.get()`/`.delete()` / un `<a :href>` simple:

```js
import { store, update, destroy } from '@/actions/App/Modules/Planning/Http/Controllers/BudgetController';
import { index as accountsIndex } from '@/routes/accounts';

form.submit(editing.value ? update(editing.value.id) : store(), { preserveScroll: true });
router.delete(destroy.url(id));
<Link :href="accountsIndex.url()">Accounts</Link>
```

`resources/js/actions/` (helpers de acciones de controlador, usados para `useForm().submit()`) y `resources/js/routes/` (helpers por nombre de ruta, usados para enlaces simples/`router.get`) se generan ambos y están en gitignore: regenéralos con `vendor/bin/sail artisan wayfinder:generate --with-form` tras cualquier cambio de rutas, no los edites a mano. Nota la bandera `--with-form`: sin ella, las acciones generadas pierden su propiedad estática `.form` usada en un par de lugares (el plugin de Vite de Wayfinder también regenera automáticamente en `npm run build`/`dev`, pero sin `--with-form`, así que vuelve a ejecutar el comando de artisan explícitamente si necesitas recuperar los helpers de formulario después de un build).

Cuando un método de controlador está enlazado a más de una ruta (p. ej., `TransactionController@index` respalda `transactions.index`, `incomes.index`, `expenses.index` y `transfers.index` mediante `Route::defaults('type', ...)`), el helper generado en `actions/` se convierte en un diccionario indexado por URI en lugar de un callable único; para esos casos, usa en su lugar el helper de ruta nombrada de `routes/` (p. ej., `import { index as transactionsIndex } from '@/routes/transactions'`).
