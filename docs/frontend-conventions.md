# Frontend Conventions

## Pages vs Components vs Layouts

- **`Pages/`** — one file per `Inertia::render('X')` target, same path (`Pages/Accounts/Index.vue` ↔ `Inertia::render('Accounts/Index', ...)`). A Page always wraps its content in `<AuthenticatedLayout>` (or `<GuestLayout>` for auth screens) and receives its data exclusively via `defineProps()` — never fetches its own initial data.
- **`Components/`** — anything reused across ≥2 Pages, or a self-contained UI primitive (`Modal`, `ModalForm`, `InputLabel`, `TextInput`, `InputError`, `PrimaryButton`/`SecondaryButton`/`DangerButton`, `EmptyState`, `StatCard`, `Money`, `CategoryIcon`, `DynamicIcon`, `Pagination`). Check here before writing a new one — most CRUD pages are built almost entirely out of existing components (see any `Pages/*/Index.vue` for the pattern: `ModalForm` for create/edit, a plain `Modal` for delete confirmation, `EmptyState` for the zero-rows case).
- **`Layouts/`** — `AuthenticatedLayout.vue` (sidebar + topbar + toast container + the global transaction modal) and `GuestLayout.vue`. A Page picks one; layouts never know about a specific Page's data.

## The established CRUD-page shape

Every domain `Index.vue` (Accounts, Categories, Budgets, SavingsGoals, RecurringTransactions) follows the same shape — copy the closest existing one rather than inventing a new structure:

```vue
<script setup>
const form = useForm({ ...defaults });
const showModal = ref(false);
const editingX = ref(null);       // null = create, set = edit
const confirmingDeleteX = ref(null);

const openCreateModal = () => { editingX.value = null; form.reset(); form.clearErrors(); showModal.value = true; };
const openEditModal = (x) => { editingX.value = x; form.clearErrors(); /* populate form.* from x */ showModal.value = true; };
const submit = () => {
    const action = editingX.value ? update(editingX.value.id) : store();
    form.submit(action, { preserveScroll: true, onSuccess: () => (showModal.value = false) });
};
</script>
```

- **A disabled `<select>`/`<input>` still submits its value.** Inertia's `useForm()` serializes the reactive JS form object directly (not a native `FormData` collected from the DOM), so `:disabled="editingBudget !== null"` on a field only prevents the *user* from changing it — the value is still sent. This mirrors the original Livewire behavior (a `wire:model`-bound property stays in server-side state regardless of `@disabled` on the rendered element) and is relied on deliberately in `Budgets/Index.vue` to lock `category_id`/`currency`/`period_type` visually on edit while still round-tripping the existing values.
- Delete confirmation is a plain `Modal`, not `ModalForm` (no form inside, just a yes/no).
- Flash messages (`back()->with('success', ...)`) are **not** currently wired to the `ToastContainer` — `useToast()` exists and `flash` is a shared prop, but no page reads `page.props.flash` to push a toast yet. This is a known gap, not a page you copied wrong.

## The global transaction modal

`Components/GlobalTransactionModal.vue` is mounted once in `AuthenticatedLayout.vue` so any page can open it — the topbar's "New transaction" button, an empty-state CTA, or a transaction row's edit action all call the same composable:

```js
import { useTransactionModal } from '@/composables/useTransactionModal';
const { open } = useTransactionModal();

open(null, 'expense');   // create, default tab = expense
open(transaction.id);    // edit — pass the transaction's *id*, not a hydrated object
```

`useTransactionModal.js` holds its state in **module-scope `ref`s** (a plain shared-singleton pattern), not Vue's `provide`/`inject` — simpler than the original plan's provide/inject sketch and equally global since ES modules are singletons. If you need the same "any component can drive shared state" pattern elsewhere, copy this composable's shape rather than reaching for `provide`/`inject` or a state library.

The modal fetches its own reference data **on open**, via plain `fetch()` against two small non-Inertia JSON endpoints (`TransactionController@formOptions`, `@editData`) rather than via shared Inertia props — deliberately, so every page doesn't have to carry `accounts`/`categories` just in case the modal opens. These `fetch()` calls need a manual CSRF header (`resources/js/csrf.js`'s `withCsrfHeader()`, reading the `XSRF-TOKEN` cookie) since they bypass Inertia's router, which handles CSRF automatically. The CSV import wizard (`Pages/Transactions/Import.vue`) uses the same `fetch()` + `withCsrfHeader()` pattern against its own `parse`/`store` JSON endpoints, for the same reason (multi-step wizard state that doesn't map to a single Inertia page visit).

## Flowbite + Inertia navigation

Flowbite's dropdowns/modals/tooltips are wired via `data-*` attributes and only auto-init on `DOMContentLoaded`. Since Inertia swaps the page without a full reload, `resources/js/app.js` re-runs the initializer after every client-side navigation:

```js
router.on('navigate', () => initFlowbite());
```

For anything rendered from a `v-for` (dropdown per row, etc.), prefer a Vue-native `ref` + `v-show`/`v-if` over relying on Flowbite's DOM re-scan finding dynamically-added elements.

## Wayfinder action helpers

Never hardcode a URL. Import the generated helper and pass it to `useForm().submit()` / `router.get()`/`.delete()` / a plain `<a :href>`:

```js
import { store, update, destroy } from '@/actions/App/Http/Controllers/BudgetController';
import { index as accountsIndex } from '@/routes/accounts';

form.submit(editing.value ? update(editing.value.id) : store(), { preserveScroll: true });
router.delete(destroy.url(id));
<Link :href="accountsIndex.url()">Accounts</Link>
```

`resources/js/actions/` (controller-action helpers, used for `useForm().submit()`) and `resources/js/routes/` (route-name helpers, used for plain links/`router.get`) are both generated and gitignored — regenerate with `vendor/bin/sail artisan wayfinder:generate --with-form` after any route change, don't hand-edit them. Note the `--with-form` flag: without it, the generated actions lose their `.form` static property used in a couple of places (Wayfinder's Vite plugin also auto-regenerates on `npm run build`/`dev`, but without `--with-form`, so re-run the artisan command explicitly if you need the form helpers back after a build).

Where a controller method is bound to more than one route (e.g. `TransactionController@index` backs `transactions.index`, `incomes.index`, `expenses.index`, and `transfers.index` via `Route::defaults('type', ...)`), the generated `actions/` helper becomes a URI-keyed dictionary rather than a single callable — for these, use the named-route helper from `routes/` instead (e.g. `import { index as transactionsIndex } from '@/routes/transactions'`).
