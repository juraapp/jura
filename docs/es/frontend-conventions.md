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

## Campos de dinero y moneda
Los campos de dinero usan `<MoneyInput v-model="form.amount" :currency="form.currency" />`, nunca `type="number"`: muestra un
prefijo de moneda e `inputmode="decimal"`, acepta cualquiera de los dos separadores y siempre emite un string decimal simple
(`"1234.50"`, `''` cuando está vacío, el texto crudo cuando no se puede leer para que aparezca el mensaje de validación del servidor). Las
reglas de análisis y visualización son `parseMoneyInput`/`formatMoneyInput` en `format.js` (también `useFormat().parseMoney` /
`formatMoney`, que muestra los separadores del usuario). Un separador solo antes de exactamente tres dígitos siempre
agrupa miles (`500.000` es 500000 para cualquier usuario), porque el dinero nunca tiene tres decimales. Los selectores de moneda usan `<CurrencySelect>`: primero la moneda por defecto del usuario, luego
`account-currencies`, luego la lista ISO común de `resources/js/currencies.js` (sincronizada con
`Shared/Support/Currencies.php`), opcionalmente con `restrict-to` a unos pocos códigos.

## Fechas por defecto
Nunca pongas por defecto un campo de fecha con `new Date().toISOString().slice(0, 10)`: esa es la fecha UTC, que ya es mañana en la
noche al oeste de Greenwich. Usa `const todayDate = useToday()` (`composables/useToday.js`) y llama a `todayDate()` cuando se
abre un formulario: es hoy en la zona horaria de las Preferencias del usuario (`resources/js/today.js` puro, probado con `npm run test:js`),
con la prop compartida `today` como respaldo.

## Mapa del dinero
`Components/MoneyMapStrip.vue` (en cuentas | en baldes | listo para asignar, en rojo "Asignado de más por" cuando es negativo) y
el `ReadyToAssignBadge.vue` de la barra superior leen la prop compartida diferida `moneyMap` dentro de `<Deferred data="moneyMap"
group="topbar">`, así que una página solo coloca `<MoneyMapStrip />`; nunca recalcules listo para asignar en el cliente.

## Pages vs Components vs Layouts

- **`Pages/`** — un archivo por cada destino de `Inertia::render('X')`, con la misma ruta (`Pages/Accounts/Index.vue` ↔ `Inertia::render('Accounts/Index', ...)`). Una Page siempre envuelve su contenido en `<AuthenticatedLayout>` (o `<GuestLayout>` para las pantallas de autenticación) y recibe sus datos exclusivamente mediante `defineProps()`; nunca obtiene por sí misma sus datos iniciales.
- **`Components/`** — todo lo que se reutiliza en ≥2 Pages, o una primitiva de UI autocontenida (`Modal`, `ModalForm`, `InputLabel`, `TextInput`, `InputError`, `PrimaryButton`/`SecondaryButton`/`DangerButton`, `EmptyState`, `StatCard`, `Money`, `CategoryIcon`, `DynamicIcon`, `Pagination`, `SelectInput`, `SegmentedControl`). Revisa aquí antes de escribir uno nuevo: la mayoría de las páginas CRUD se construyen casi por completo con componentes existentes (mira cualquier `Pages/*/Index.vue` para ver el patrón: `ModalForm` para crear/editar, un `Modal` simple para confirmar el borrado, `EmptyState` para el caso de cero filas).
- **`Layouts/`** — `AuthenticatedLayout.vue` (barra lateral + barra superior + contenedor de toasts + el modal global de transacciones) y `GuestLayout.vue`. Una Page elige uno; los layouts nunca conocen los datos de una Page específica.

**Pantallas de autenticación.** Constrúyelas con `GuestLayout` (layout dividido: panel de marca verde en escritorio solo con el logo y una ilustración animada (prop `illustration`: `wallet` o `envelope`; `AuthIllustration` hace la transición entre ellas al cambiar de página), sin eslóganes ni listas de funciones; slot `#footer` para enlaces) más `AuthField` (etiqueta, icono inicial, error y botón mostrar/ocultar para contraseñas), `AuthButton` y `AuthStatus`, no con los genéricos `TextInput`/`PrimaryButton`.

**Página de inicio.** `Pages/Welcome.vue` es lo que ven los invitados en `/` (la ruta `home`; los usuarios con sesión iniciada se redirigen al dashboard). Tiene su propio layout de página completa, no `GuestLayout`. Enlaza a ella con el `home()` de Wayfinder desde `@/routes`, nunca con un `"/"` escrito a mano, y muestra el nombre de la marca desde `$page.props.appName`, nunca un "Jura" literal.

## La estructura establecida de las páginas CRUD

Cada `Index.vue` de dominio (Accounts, Categories, Buckets) sigue la misma estructura: copia la más parecida existente en lugar de inventar una nueva:

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

- **Un `<select>`/`<input>` deshabilitado igualmente envía su valor.** El `useForm()` de Inertia serializa directamente el objeto reactivo de JS del formulario (no un `FormData` nativo recolectado desde el DOM), así que `:disabled="editingBucket !== null"` en un campo solo impide que el *usuario* lo cambie: el valor se envía de todos modos. Esto replica el comportamiento original de Livewire (una propiedad enlazada con `wire:model` se mantiene en el estado del servidor sin importar `@disabled` en el elemento renderizado) y se usa deliberadamente en `Buckets/Index.vue` para bloquear visualmente `currency` al editar, devolviendo aun así el valor existente.
- La confirmación de borrado es un `Modal` simple, no un `ModalForm` (sin formulario dentro, solo un sí/no).
- Los mensajes flash (`back()->with('success'|'error'|'warning', ...)`, más `undo`) se muestran como toasts mediante `ToastContainer`; las páginas no leen `page.props.flash` por su cuenta.
- El texto del diálogo de borrado debe coincidir con lo que ocurre: di que el registro se puede restaurar desde la papelera para los modelos con soft delete (transacciones, cuentas, categorías) y usa `ui.common.cannot_be_undone` solo para los borrados definitivos.

## Encabezado y ancho de página

Una página coloca `<PageHeader>` en el slot `#header` del layout: `title`, `subtitle` opcional, `back` opcional (`{ href,
label }`, un enlace encima del título), un slot `#actions` (alineado a la derecha, pasa debajo del título en teléfonos) y una fila `#meta`.
`<AuthenticatedLayout width="wide">` (max-w-7xl: listas, tablas, dashboards) o `width="narrow"` (max-w-3xl: formularios y
ajustes) hace que el layout controle el ancho: el encabezado y el cuerpo comparten un contenedor con el padding de la página, así que el
cuerpo de la página quita su propio envoltorio `mx-auto max-w-* px-*`. Todas las páginas ya lo hacen; `width` sigue siendo opcional solo para las
pantallas de invitado/autenticación. La acción principal de una página (Nueva cuenta, Nueva transacción, una exportación) va en `#actions`; usa
`PrimaryButton`/`SecondaryButton` (con `:as="Link"` para un enlace), nunca botones con estilos a mano.

La barra lateral tiene cuatro grupos: Dinero (dashboard, cuentas, transacciones, importar), Plan (próximos y flujo de caja, baldes,
proyectos, lista de deseos, planificador), Informes (los reportes) y Configuración. Un elemento puede listar rutas `also` que le pertenecen:
las listas de ingresos, gastos y transferencias son pestañas de la página de Transacciones, así que Transacciones sigue resaltado en ellas.

## Estados vacíos

`<EmptyState>` recibe su acción principal como props: `action-label`, `action-icon` opcional y `action-href` (renderizado como un
enlace `PrimaryButton`) o, sin enlace, un evento `@action` (un clic de `PrimaryButton`). Usa el slot `#action` solo
para algo que un único botón principal no puede expresar.

## Botones

Usa los componentes compartidos, nunca un elemento `bg-primary-600` con estilos a mano:

- `PrimaryButton` (acción principal), `SecondaryButton` (con contorno, secundario o cancelar), `DangerButton` (destructivo). Comparten el aspecto de la marca: `rounded-lg`, mayúscula solo inicial, `text-sm font-medium`, un anillo `focus:ring-4` y estilos `disabled:` distintos en modo claro y oscuro. Su contenido se espacia con `gap-1.5`, así que un icono no necesita margen (`<DynamicIcon name="plus" class="h-4 w-4" />`).
- `PrimaryButton` y `DangerButton` tienen `type="submit"` por defecto (el valor por defecto de HTML del que dependen los formularios de autenticación y perfil), `SecondaryButton` tiene `type="button"`. Pasa `type="button"` para un manejador de clic que no sea un envío de formulario.
- Para navegación o descargas, renderiza el mismo aspecto como enlace con `as`: `<PrimaryButton :as="Link" :href="...">` para páginas de Inertia, `<PrimaryButton as="a" :href="pdfUrl" target="_blank">` para archivos. Nunca anides un botón dentro de un `Link`.
- Los botones solo con icono son `<IconButton icon="trash" :label="$t('ui.common.delete')" variant="danger" @click="..." />`: el `label` obligatorio se convierte tanto en el tooltip como en el nombre accesible. Variantes: `ghost` (por defecto), `danger`, `primary`; `as` renderiza un enlace.
- Los campos de texto, selects, textareas y checkboxes comparten un aspecto (`rounded-lg`, anillo de foco primario, relleno oscuro `gray-700`): `TextInput` (v-model string o number), `SelectInput`, `Textarea`, `Checkbox`.

## Llamadas fetch() simples

Los endpoints JSON llamados con `fetch()` (modal global de transacciones, asistente de importación, historial de baldes, planificador) deben verificar `response.ok`, capturar los errores de red y avisarle al usuario, mediante `useToast().push({ type: 'error', message })` o un error en línea con opción de reintentar, nunca mostrando en silencio un estado vacío o de "listo". Cuando una petición más nueva puede reemplazar a una anterior (ejecuciones del planificador, abrir otra transacción), numera las peticiones e ignora las respuestas que ya no son las más recientes. Las etiquetas construidas con `t()` en `<script setup>` van en un `computed`, para que sigan un cambio de idioma sin recargar.

## El modal global de transacciones (un único formulario de movimientos de dinero)

`Components/GlobalTransactionModal.vue` se monta una sola vez en `AuthenticatedLayout.vue`. Es un envoltorio delgado de `ModalForm`
alrededor de `Components/MoneyMovementForm.vue`, el único formulario para transacciones reales, planes, planes repetitivos y la confirmación de un
plan. Cualquier página lo maneja mediante el composable:

```js
import { useTransactionModal } from '@/composables/useTransactionModal';
const { open, openPlanned, openSeries, openConfirm } = useTransactionModal();

open(null, 'expense');           // crear una transacción real, pestaña por defecto = gasto
open(null, 'income', 'planned'); // crear, empezando en "Planeado"
open(transaction.id);            // editar una transacción: pasa su *id*, no un objeto hidratado
openPlanned(null, { type: 'income' }); // plan nuevo
openPlanned(event);              // editar un plan (un evento planificado presentado: PlannedEventPresenter::event)
openSeries(series.id);           // editar un plan repetitivo completo (obtiene planned-series.edit-data)
openConfirm(event);              // registrar un plan como real, prellenado; fecha = la del plan, u hoy si es posterior
```

Las intenciones del formulario son `create`, `editTransaction`, `editPlanned`, `editSeries` y `confirmPlanned`; solo `create`
muestra el selector **Ya ocurrió / Planeado** y las pestañas de tipo. Sus reglas (a qué ruta enviar, el cuerpo de la
petición, la pregunta por fecha futura, desde qué baldes se puede pagar un gasto) viven en el `resources/js/moneyMovement.js` puro,
probado con `npm run test:js`. El balde guardado de un gasto se obtiene con `spend-options?include=<id>` y se conserva al cargar;
solo un cambio que hace el usuario (cuenta o tipo) puede quitarlo (`keepBucket`).

`useTransactionModal.js` mantiene su estado en **`ref`s de ámbito de módulo** (un patrón simple de singleton compartido), no en
`provide`/`inject` de Vue. Si necesitas el mismo patrón de "cualquier componente puede manejar un estado compartido" en otro lugar, copia
la forma de este composable en lugar de recurrir a `provide`/`inject` o a una librería de estado.

El envoltorio obtiene los datos de referencia **bajo demanda** mediante `fetch()` simple contra pequeños endpoints JSON que no son de Inertia en lugar de
props compartidas de Inertia: `TransactionController@formOptions` una vez (cuentas con `id`, `name`, `currency`, `on_budget`, y
categorías), `PlannedEventController@formOptions` (`planned-events.form-options`: frecuencias, certezas, baldes) la
primera vez que se muestra un plan en cada apertura (para que aparezca un balde nuevo), y en cada apertura el formulario obtiene `editData`, `planned-series.edit-data` y las
`spend-options` del balde. Estas llamadas `fetch()` necesitan un encabezado CSRF manual (`withCsrfHeader()` de `resources/js/csrf.js`, que lee
la cookie `XSRF-TOKEN`) cuando no son GET, ya que omiten el router de Inertia. El asistente de importación CSV
(`Pages/Transactions/Import.vue`) usa el mismo patrón `fetch()` + `withCsrfHeader()` contra sus propios endpoints
JSON `parse`/`store`.

## Proyectos
`Pages/Projects/Index.vue` (tarjetas) y `Show.vue` (un proyecto) obtienen cada número de `ProjectOverview` (nunca
recalcules lo financiado ni el presupuesto restante en el cliente). `Components/ProjectModal.vue` crea y edita un proyecto y es el
único formulario de proyectos: las páginas de Proyectos y la lista de deseos lo usan. La página de un proyecto enlaza a
`/planning/wishlist?project=<id>`, que abre el formulario de ítems de la lista de deseos con ese proyecto elegido (`newItemProject`). El formulario de ítems puede crear un proyecto en el momento (`POST planning/projects/quick`, `fetch()` simple con `withCsrfHeader`, y luego lo elige) y ofrece tres formas de pagar: listo para asignar (o el balde del proyecto), un balde existente o un balde nuevo creado para el ítem (`new_bucket` más una regla y un valor de relleno).

## Filas planificadas

`Components/PlannedBand.vue` (las franjas de vencidos y próximos en la lista de transacciones, a partir de la prop diferida `planned`,
que Inertia recarga después de cada visita que devuelve la página, incluidas las redirecciones de POST), la lista de flujo de caja y
la bandeja de revisión comparten `composables/usePlanActions.js`: **Confirmar** (un clic mediante `planned-events.realize` cuando
el plan tiene una cuenta y una categoría, si no `openConfirm`), **Ajustar** (`openConfirm`), **Omitir**, **Emparejar** (vincular
la transacción sugerida) y **Editar** (`openPlanned`). Las filas proyectadas se etiquetan "Proyectado, no está en tu saldo"
y nunca se mezclan en los totales reales.

## Navegación de Flowbite + Inertia

Los dropdowns/modales/tooltips de Flowbite se conectan mediante atributos `data-*` y solo se inicializan automáticamente en `DOMContentLoaded`. Como Inertia intercambia la página sin una recarga completa, `resources/js/app.js` vuelve a ejecutar el inicializador después de cada navegación del lado del cliente:

```js
router.on('navigate', () => initFlowbite());
```

Para cualquier cosa renderizada desde un `v-for` (un dropdown por fila, etc.), prefiere un `ref` + `v-show`/`v-if` nativos de Vue en lugar de confiar en que el re-escaneo del DOM de Flowbite encuentre los elementos agregados dinámicamente.

## Helpers de acciones de Wayfinder

Los helpers de acciones se generan a partir del nombre completo de la clase de cada controlador, así que la ruta de importación incluye su módulo (`@/actions/App/Modules/<Module>/Http/Controllers/<Name>Controller`). Mover o renombrar un controlador implica regenerar (`vendor/bin/sail artisan wayfinder:generate --with-form`) y actualizar los imports; `npm run build` falla de forma evidente si alguno está desactualizado. Nunca escribas una URL a mano. Importa el helper generado y pásalo a `useForm().submit()` / `router.get()`/`.delete()` / un `<a :href>` simple:

```js
import { store, update, destroy } from '@/actions/App/Modules/Planning/Http/Controllers/BucketController';
import { index as accountsIndex } from '@/routes/accounts';

form.submit(editing.value ? update(editing.value.id) : store(), { preserveScroll: true });
router.delete(destroy.url(id));
<Link :href="accountsIndex.url()">Accounts</Link>
```

`resources/js/actions/` (helpers de acciones de controlador, usados para `useForm().submit()`) y `resources/js/routes/` (helpers por nombre de ruta, usados para enlaces simples/`router.get`) se generan ambos y están en gitignore: regenéralos con `vendor/bin/sail artisan wayfinder:generate --with-form` tras cualquier cambio de rutas, no los edites a mano. Nota la bandera `--with-form`: sin ella, las acciones generadas pierden su propiedad estática `.form` usada en un par de lugares (el plugin de Vite de Wayfinder también regenera automáticamente en `npm run build`/`dev`, pero sin `--with-form`, así que vuelve a ejecutar el comando de artisan explícitamente si necesitas recuperar los helpers de formulario después de un build).

Cuando un método de controlador está enlazado a más de una ruta (p. ej., `TransactionController@index` respalda `transactions.index`, `incomes.index`, `expenses.index` y `transfers.index` mediante `Route::defaults('type', ...)`), el helper generado en `actions/` se convierte en un diccionario indexado por URI en lugar de un callable único; para esos casos, usa en su lugar el helper de ruta nombrada de `routes/` (p. ej., `import { index as transactionsIndex } from '@/routes/transactions'`).
