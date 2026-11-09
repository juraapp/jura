# Arquitectura

## Stack

- **Backend**: Laravel 13, PHP 8.5.
- **Frontend**: Inertia.js v3 + Vue 3 (Composition API, `<script setup>`), Tailwind CSS v3 + Flowbite, vue3-apexcharts.
- **Enrutamiento**: rutas clásicas de Laravel (`routes/web.php`) que devuelven respuestas de Inertia en lugar de vistas Blade. [Laravel Wayfinder](https://github.com/laravel/wayfinder) genera helpers TS tipados para cada ruta y acción de controlador, que se regeneran con:

  ```bash
  vendor/bin/sail artisan wayfinder:generate --with-form
  ```

  Los directorios generados `resources/js/actions/` y `resources/js/routes/` están en gitignore: regenéralos después de cualquier cambio de rutas y no los edites a mano.
- **Base de datos**: MariaDB. **Caché/sesiones**: Redis.
- **Exportación a PDF**: `spatie/laravel-pdf`, configurado con su driver **dompdf** en PHP puro (`LARAVEL_PDF_DRIVER=dompdf` en `.env`) en lugar del driver Browsershot por defecto, específicamente para evitar requerir Chrome headless dentro del contenedor de Sail. `dompdf/dompdf` es una dependencia real de ejecución aunque nada en `app/` importe directamente una clase de Dompdf: la conexión se hace por completo mediante esa variable de entorno, así que un grep de "nada referencia esto" no basta para concluir que no se usa.
- **Entorno local**: Laravel Sail (Docker). Todos los comandos artisan/composer/npm se ejecutan a través de `vendor/bin/sail`.

## Ciclo de vida de una petición

```
Browser → routes/web.php → Controller → Form Request (validation + authorize())
        → Service (business logic) → Model
        → Inertia::render('Pages/Component', [...]) → resources/js/Pages/Component.vue
```

- Los controladores se mantienen delgados: la validación vive en `Http/Requests/{Domain}/{Store,Update}...Request.php` de cada módulo, y la lógica de negocio vive en `Services/*Service.php` del módulo. La validación por componente de Livewire no tenía equivalente en controladores, así que cada módulo de dominio recibió un par de Form Requests correspondiente durante la migración.
- Los valores devueltos por `Inertia::render()` se convierten en las `props` de la página. Las props son arrays/colecciones simples de primitivos: los modelos Eloquent y los objetos de valor `App\Modules\Shared\Support\Money` nunca se pasan directamente; los controladores los convierten antes a floats/strings (`$money->toFloat()`, estructuración al estilo `$model->toArray()`). Consulta cualquier `*Controller@index` para ver el patrón.
- Las acciones que mutan datos (`store`/`update`/`destroy`) devuelven `back()->with('success', __('domain.saved'))` en lugar de una redirección de Inertia a otra página: simplemente se vuelven a obtener las props de la página actual.
- Existen unos pocos endpoints JSON (no Inertia) junto a los de Inertia, para datos que una página necesita obtener bajo demanda en lugar de recibirlos como prop: `TransactionController@formOptions`/`editData` (alimenta el modal global de transacciones, ver `frontend-conventions.md`) y `TransactionImportController@parse`/`store` (alimenta el asistente de importación CSV). Son respuestas `response()->json(...)` simples consumidas mediante `fetch()`, no `router.get()`/`useForm()`.

## Módulos

El código del backend vive en `app/Modules/<Module>/` (namespaces simples `App\Modules\<Module>\...`, sin paquete). Cada módulo tiene solo las carpetas que necesita: `Models/ Services/ Http/{Controllers,Requests}/ Policies/ Enums/ Events/ Listeners/ Contracts/ ...`. Las factories permanecen en `database/factories/`; `SharedServiceProvider` encuentra los modelos movidos. Las rutas, los nombres de ruta, las tablas y `resources/js` no se dividen por módulo.

| Módulo | Es dueño de |
|---|---|
| `Shared` | `Money`, `AsMoney`, `BelongsToUser`, `Controller` base, `HandleInertiaRequests` |
| `Identity` | `User`, autenticación, perfil, preferencias |
| `Ledger` | `Account`, `Category`, `Transaction`, saldos, importación CSV, `TransactionsMutated`. Fuente de verdad de los datos **reales**. |
| `Planning` | `PlannedEvent`, `Bucket`, `Allocation`, `SavingsGoal`, `RecurringTransaction` (y, a continuación, lista de deseos y escenarios). Es dueño del dinero **proyectado** y **apartado** (virtual). |
| `Reporting` | dashboard, reportes, insights, exportación PDF/CSV (lado de lectura) |
| `Notifications` | notificaciones de alertas, sus listeners y comandos programados, la bandeja de notificaciones (lado de lectura) |

**Reglas de frontera** (aplicadas por `tests/Architecture/ModuleBoundariesTest.php`):

1. Siempre permitido entre módulos: `Shared`, los `Contracts`, `Events` y `Enums` de otro módulo, e `Identity\Models\User`.
2. Los `Models` de otro módulo solo pueden referenciarse desde archivos bajo un directorio `Models/` (relaciones) o desde los módulos de lado de lectura `Reporting` y `Notifications`.
3. Nada más (`Services`, `Http`, `Policies`, `Jobs`, ...) de otro módulo puede importarse. Las llamadas entre módulos pasan por contratos:
   - `Ledger\Contracts\Balances`: saldos de cuentas y patrimonio neto.
   - `Ledger\Contracts\LedgerQuery`: totales de gastos, listas de opciones de cuentas/categorías, moneda de la cuenta y búsqueda de transacciones similares.
   - `Ledger\Contracts\RecordsTransactions`: la única forma en que otros módulos crean transacciones (`record`, `recordRecurring`).
   - `Planning\Contracts\BucketProgress`: gastado/restante/estado de los buckets de gasto.
   - `Planning\Contracts\CashFlowForecast`: saldos proyectados mes a mes y dinero planificado en una ventana de fechas.
   - `Planning\Contracts\PlanningOverview`: saldos de buckets, historial de asignaciones, saldos proyectados de buckets y resúmenes de proyectos para reportes.
4. Los contratos se enlazan en el service provider de cada módulo; los providers se listan en `bootstrap/providers.php`.

Para agregar un módulo: crea `app/Modules/<Name>/`, agrega un `<Name>ServiceProvider` a `bootstrap/providers.php` si enlaza contratos o registra listeners/comandos, y ejecuta el test de arquitectura. Los listeners y comandos dentro de los módulos **no se autodescubren**; regístralos en el provider del módulo.

## Props compartidas

`app/Modules/Shared/Http/Middleware/HandleInertiaRequests.php` comparte en cada petición:

- `appName` — desde `config('app.name')`.
- `i18n` — `{ locale, messages }`, la única fuente de verdad para las cadenas renderizadas tanto en el servidor como en el cliente (ver `i18n-conventions.md`).
- `auth.user` — el usuario autenticado, o `null`.
- `flash` — `{ success, error, warning }`, leído por las páginas que quieren reaccionar a un mensaje flash (la mayoría actualmente depende de que el flash aparezca de forma natural mediante un re-renderizado completo de Inertia en lugar de leerlo explícitamente).
- `netWorth` / `notifications` — diferidas (`Inertia::defer(..., 'topbar')`) porque la barra superior las necesita en cada página pero no vale la pena bloquear el renderizado inicial por ellas. Los componentes Vue `NetWorthBadge`/`NotificationBell` las consumen; ver `frontend-conventions.md`.

## Estructura de directorios del frontend

```
resources/js/
  Pages/            One .vue file per Inertia::render() target. Directory structure mirrors
                     the "domain module" (Pages/Accounts/Index.vue, Pages/Reports/Monthly.vue, ...).
  Components/       Shared, reusable Vue components (ModalForm, EmptyState, StatCard, Money,
                     CategoryIcon, DynamicIcon, GlobalTransactionModal, Sidebar, Topbar, ...).
  Layouts/           AuthenticatedLayout.vue, GuestLayout.vue — mounted by each Page via
                     <AuthenticatedLayout><template #header>...</template>...</AuthenticatedLayout>.
  composables/       useDarkMode, useSidebar, useToast, useTransactionModal, onClickOutside —
                     Vue composition functions, several using module-scope refs as a lightweight
                     global-store pattern (no Pinia in this app).
  actions/, routes/  Wayfinder-generated, gitignored. Regenerate after route changes.
  app.js             Inertia + Vue app bootstrap, vue-i18n setup, Flowbite re-init hook.
  csrf.js            Reads the XSRF-TOKEN cookie for the few hand-rolled fetch() calls that
                     bypass Inertia's router (see frontend-conventions.md).
  chartPalette.js    Shared categorical/sequential color palette for vue3-apexcharts.
```

Nota de configuración: `config/inertia.php` sobrescribe el valor por defecto `pages.paths` del paquete (`resource_path('js/pages')`, en minúsculas) con `resource_path('js/Pages')` para coincidir con el directorio real con mayúscula inicial de este proyecto: en un sistema de archivos que distingue mayúsculas de minúsculas, el valor por defecto del vendor no logra encontrar ningún componente de página cuando `assertInertia()->component()` verifica que el archivo exista (`ensure_pages_exist`). Si las aserciones de páginas de Inertia en los tests empiezan a fallar con "page component file does not exist", revisa primero este archivo.

Consulta `domain-model.md` para la capa de datos, `frontend-conventions.md` para las convenciones del lado de Vue, `i18n-conventions.md` para las traducciones, `dark-mode.md` para el sistema de temas y `testing-conventions.md` para la suite de tests.
