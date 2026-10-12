# Architecture

## Stack

- **Backend**: Laravel 13, PHP 8.5.
- **Frontend**: Inertia.js v3 + Vue 3 (Composition API, `<script setup>`), Tailwind CSS v3 + Flowbite, vue3-apexcharts.
- **Routing**: classic Laravel routes (`routes/web.php`) rendering Inertia responses instead of Blade views. [Laravel Wayfinder](https://github.com/laravel/wayfinder) generates typed TS helpers for every route and controller action, regenerated with:

  ```bash
  vendor/bin/sail artisan wayfinder:generate --with-form
  ```

  The generated `resources/js/actions/` and `resources/js/routes/` directories are gitignored — regenerate them after any route change, don't hand-edit them.
- **Database**: MariaDB. **Cache/sessions**: Redis.
- **PDF export**: `spatie/laravel-pdf`, configured with its pure-PHP **dompdf** driver (`LARAVEL_PDF_DRIVER=dompdf` in `.env`) rather than the default Browsershot driver, specifically to avoid requiring headless Chrome inside the Sail container. `dompdf/dompdf` is a real runtime dependency even though nothing in `app/` imports a Dompdf class directly — the wiring is entirely through that env var, so a "nothing references this" grep is not sufficient to conclude it's unused.
- **Local environment**: Laravel Sail (Docker). All artisan/composer/npm commands run through `vendor/bin/sail`.

## Request lifecycle

```
Browser → routes/web.php → Controller → Form Request (validation + authorize())
        → Service (business logic) → Model
        → Inertia::render('Pages/Component', [...]) → resources/js/Pages/Component.vue
```

- Controllers stay thin: validation lives in each module's `Http/Requests/{Domain}/{Store,Update}...Request.php`, business logic lives in the module's `Services/*Service.php`. Livewire's per-component validation had no controller equivalent, so every domain module got a matching Form Request pair during the migration.
- `Inertia::render()` return values become the page's `props`. Props are plain arrays/collections of primitives — Eloquent models and `App\Modules\Shared\Support\Money` value objects are never passed directly; controllers map them to floats/strings first (`$money->toFloat()`, `$model->toArray()`-style shaping). See any `*Controller@index` for the pattern.
- Mutating actions (`store`/`update`/`destroy`) return `back()->with('success', __('domain.saved'))` rather than an Inertia redirect to a different page — the current page's props are simply re-fetched.
- A handful of JSON (non-Inertia) endpoints exist alongside the Inertia ones, for data a page needs to fetch on demand rather than as a prop: `TransactionController@formOptions`/`editData` (feeds the global transaction modal, see `frontend-conventions.md`) and `TransactionImportController@parse`/`store` (feeds the CSV import wizard). These are plain `response()->json(...)` responses hit via `fetch()`, not `router.get()`/`useForm()`.

## Modules

Backend code lives in `app/Modules/<Module>/` (plain `App\Modules\<Module>\...` namespaces, no package). Each module has only the folders it needs: `Models/ Services/ Http/{Controllers,Requests}/ Policies/ Enums/ Events/ Listeners/ Contracts/ ...`. Factories stay in `database/factories/`; `SharedServiceProvider` finds the moved models. Routes, route names, tables and `resources/js` are not split by module.

| Module | Owns |
|---|---|
| `Shared` | `Money`, `AsMoney`, `BelongsToUser`, base `Controller`, `HandleInertiaRequests` |
| `Identity` | `User`, auth, profile, preferences |
| `Ledger` | `Account`, `Category`, `Transaction`, balances, CSV import, `TransactionsMutated`. Source of truth for **actual** data. |
| `Planning` | `PlannedEvent`, `Bucket`, `Allocation`, `SavingsGoal`, `RecurringTransaction` (and, next, wish list and scenarios). Owns **projected** and **earmarked** (virtual) money. |
| `Reporting` | dashboard, reports, insights, PDF/CSV export (read-side) |
| `Notifications` | alert notifications, their listeners and scheduled commands, the notification inbox (read-side) |

**Boundary rules** (enforced by `tests/Architecture/ModuleBoundariesTest.php`):

1. Always allowed across modules: `Shared`, another module's `Contracts`, `Events`, `Enums`, and `Identity\Models\User`.
2. Another module's `Models` may be referenced only from files under a `Models/` directory (relationships) or from the read-side modules `Reporting` and `Notifications`.
3. Nothing else (`Services`, `Http`, `Policies`, `Jobs`, ...) of another module may be imported. Cross-module calls go through contracts:
   - `Ledger\Contracts\Balances`: account balances and net worth.
   - `Ledger\Contracts\LedgerQuery`: expense totals, account/category option lists, account currency and similar-transaction lookups.
   - `Ledger\Contracts\RecordsTransactions`: the only way other modules create transactions (`record`, `recordRecurring`).
   - `Planning\Contracts\BucketProgress`: spending-bucket spent/remaining/status.
4. Contracts are bound in each module's service provider; providers are listed in `bootstrap/providers.php`.

To add a module: create `app/Modules/<Name>/`, add a `<Name>ServiceProvider` to `bootstrap/providers.php` if it binds contracts or registers listeners/commands, and run the architecture test. Listeners and commands inside modules are **not auto-discovered**; register them in the module provider.

## Shared props

`app/Modules/Shared/Http/Middleware/HandleInertiaRequests.php` shares on every request:

- `appName` — from `config('app.name')`.
- `i18n` — `{ locale, messages }`, the single source of truth for both server- and client-rendered strings (see `i18n-conventions.md`).
- `auth.user` — the authenticated user, or `null`.
- `flash` — `{ success, error, warning }`, read by pages that want to react to a flash message (most currently rely on the flash surfacing naturally via a full Inertia re-render rather than reading this explicitly).
- `netWorth` / `notifications` — deferred (`Inertia::defer(..., 'topbar')`) since the topbar needs them on every page but they're not worth blocking the initial render for. The Vue-side `NetWorthBadge`/`NotificationBell` components consume them; see `frontend-conventions.md`.

## Frontend directory layout

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

Config note: `config/inertia.php` overrides the package's `pages.paths` default (`resource_path('js/pages')`, lowercase) to `resource_path('js/Pages')` to match this project's actual capitalized directory — on a case-sensitive filesystem the vendor default silently fails to find any page component when `assertInertia()->component()` checks the file exists (`ensure_pages_exist`). If Inertia page assertions in tests start failing with "page component file does not exist", check this file first.

See `domain-model.md` for the data layer, `frontend-conventions.md` for Vue-side conventions, `i18n-conventions.md` for translations, `dark-mode.md` for the theme system, and `testing-conventions.md` for the test suite.
