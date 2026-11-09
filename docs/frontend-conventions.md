# Frontend Conventions

## Lists with bulk actions
Use `useBulkSelection(() => rows)` with `<BulkCheckbox>` per row (and `page` in the header) and `<BulkDeleteBar>` for plain
delete-many; add a `bulkDestroy` action on the controller taking `ids[]` (`Shared\Http\Requests\BulkIdsRequest`, max 500).
Server flashes (`success`/`error`/`warning`, plus `undo: {url, ids}`) are shown as toasts by `ToastContainer`.

## Formatting dates, times, numbers and money
Never call `toLocaleDateString`, `toFixed` or `Intl.NumberFormat` for user-visible values. Use `useFormat()`
(`resources/js/composables/useFormat.js`: `date`, `time`, `dateTime`, `number`, `money`) so the user's
Preferences (date style, 12/24h, separators, decimals, currency display) apply everywhere. `<Money>` and the
chart helpers in `chartPalette.js` already do. The rules live in `resources/js/format.js` and are mirrored for the
server by `App\Modules\Shared\Support\UserFormat` (PDFs, notifications); change both together. `format.js` has no
framework imports and is tested with `npm run test:js` (`node --test`).

## Money and currency fields
Money fields use `<MoneyInput v-model="form.amount" :currency="form.currency" />`, never `type="number"`: it shows a
currency prefix and `inputmode="decimal"`, accepts either separator and always emits a plain decimal string
(`"1234.50"`, `''` when blank, the raw text when it cannot be read so the server's validation message shows). The
parsing and display rules are `parseMoneyInput`/`formatMoneyInput` in `format.js` (also `useFormat().parseMoney` /
`formatMoney` with the user's separators). Currency pickers use `<CurrencySelect>`: the user's default first, then
`account-currencies`, then the common ISO list from `resources/js/currencies.js` (kept in step with
`Shared/Support/Currencies.php`), optionally `restrict-to` a few codes.

## Default dates
Never default a date field with `new Date().toISOString().slice(0, 10)`: that is the UTC date, already tomorrow in the
evening west of Greenwich. Use `const todayDate = useToday()` (`composables/useToday.js`) and call `todayDate()` when a
form opens: it is today in the user's Preferences timezone (pure `resources/js/today.js`, tested with `npm run test:js`),
falling back to the shared `today` prop.

## Money map
`Components/MoneyMapStrip.vue` (in accounts | in buckets | ready to assign, red "Over-assigned by" when negative) and
the topbar's `ReadyToAssignBadge.vue` read the shared deferred `moneyMap` prop inside `<Deferred data="moneyMap"
group="topbar">`, so a page only drops `<MoneyMapStrip />` in; never recompute ready to assign on the client.

## Pages vs Components vs Layouts

- **`Pages/`** — one file per `Inertia::render('X')` target, same path (`Pages/Accounts/Index.vue` ↔ `Inertia::render('Accounts/Index', ...)`). A Page always wraps its content in `<AuthenticatedLayout>` (or `<GuestLayout>` for auth screens) and receives its data exclusively via `defineProps()` — never fetches its own initial data.
- **`Components/`** — anything reused across ≥2 Pages, or a self-contained UI primitive (`Modal`, `ModalForm`, `InputLabel`, `TextInput`, `InputError`, `PrimaryButton`/`SecondaryButton`/`DangerButton`, `EmptyState`, `StatCard`, `Money`, `CategoryIcon`, `DynamicIcon`, `Pagination`, `SelectInput`, `SegmentedControl`). Check here before writing a new one — most CRUD pages are built almost entirely out of existing components (see any `Pages/*/Index.vue` for the pattern: `ModalForm` for create/edit, a plain `Modal` for delete confirmation, `EmptyState` for the zero-rows case).
- **`Layouts/`** — `AuthenticatedLayout.vue` (sidebar + topbar + toast container + the global transaction modal) and `GuestLayout.vue`. A Page picks one; layouts never know about a specific Page's data.

## The established CRUD-page shape

Every domain `Index.vue` (Accounts, Categories, Buckets, SavingsGoals) follows the same shape — copy the closest existing one rather than inventing a new structure:

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

- **A disabled `<select>`/`<input>` still submits its value.** Inertia's `useForm()` serializes the reactive JS form object directly (not a native `FormData` collected from the DOM), so `:disabled="editingBucket !== null"` on a field only prevents the *user* from changing it — the value is still sent. This mirrors the original Livewire behavior (a `wire:model`-bound property stays in server-side state regardless of `@disabled` on the rendered element) and is relied on deliberately in `Buckets/Index.vue` to lock `currency` visually on edit while still round-tripping the existing value.
- Delete confirmation is a plain `Modal`, not `ModalForm` (no form inside, just a yes/no).
- Flash messages (`back()->with('success'|'error'|'warning', ...)`, plus `undo`) are shown as toasts by `ToastContainer`; pages do not read `page.props.flash` themselves.
- Delete-dialog copy must match what happens: say the record can be restored from the Trash for soft-deleted models (transactions, accounts, categories, savings goals) and use `ui.common.cannot_be_undone` only for hard deletes.

## Page header and width

A page puts `<PageHeader>` in the layout's `#header` slot: `title`, optional `subtitle`, optional `back` (`{ href,
label }`, a link above the title), an `#actions` slot (right-aligned, wraps under the title on phones) and a `#meta` row.
`<AuthenticatedLayout width="wide">` (max-w-7xl: lists, tables, dashboards) or `width="narrow"` (max-w-3xl: forms and
settings) makes the layout own the width: the header and the body share one container with the page padding, so the
page body drops its own `mx-auto max-w-* px-*` wrapper. Without `width` a page still sizes its own body (older pages
until they adopt it).

## Empty states

`<EmptyState>` takes its main action as props: `action-label`, optional `action-icon`, and `action-href` (rendered as a
`PrimaryButton` link) or, without a link, an `@action` event (a `PrimaryButton` click). Use the `#action` slot only
for something a single primary button cannot express.

## Buttons

Use the shared components, never a hand-styled `bg-primary-600` element:

- `PrimaryButton` (main action), `SecondaryButton` (outlined, secondary or cancel), `DangerButton` (destructive). They share the brand look: `rounded-lg`, sentence case, `text-sm font-medium`, a `focus:ring-4` ring and distinct `disabled:` styles in light and dark mode. Their content is spaced with `gap-1.5`, so an icon needs no margin (`<DynamicIcon name="plus" class="h-4 w-4" />`).
- `PrimaryButton` and `DangerButton` default to `type="submit"` (the HTML default the auth and profile forms rely on), `SecondaryButton` to `type="button"`. Pass `type="button"` for a click handler that is not a form submit.
- For navigation or downloads, render the same look as a link with `as`: `<PrimaryButton :as="Link" :href="...">` for Inertia pages, `<PrimaryButton as="a" :href="pdfUrl" target="_blank">` for files. Never nest a button inside a `Link`.
- Icon-only buttons are `<IconButton icon="trash" :label="$t('ui.common.delete')" variant="danger" @click="..." />`: the required `label` becomes both the tooltip and the accessible name. Variants: `ghost` (default), `danger`, `primary`; `as` renders a link.
- Text fields, selects, textareas and checkboxes share one look (`rounded-lg`, primary focus ring, dark `gray-700` fill): `TextInput` (v-model string or number), `SelectInput`, `Textarea`, `Checkbox`.

## Plain fetch() calls

JSON endpoints called with `fetch()` (global transaction modal, import wizard, bucket history, planner) must check `response.ok`, catch network errors and tell the user, through `useToast().push({ type: 'error', message })` or an inline error with a retry, never by silently showing an empty or "done" state. When a newer request can supersede an older one (planner runs, opening another transaction), number the requests and ignore responses that are no longer the latest. Labels built with `t()` in `<script setup>` go in a `computed`, so they follow a language switch without a reload.

## The global transaction modal (one money-movement form)

`Components/GlobalTransactionModal.vue` is mounted once in `AuthenticatedLayout.vue`. It is a thin `ModalForm` shell
around `Components/MoneyMovementForm.vue`, the one form for real transactions, plans, repeating plans and confirming a
plan. Any page drives it through the composable:

```js
import { useTransactionModal } from '@/composables/useTransactionModal';
const { open, openPlanned, openSeries, openConfirm } = useTransactionModal();

open(null, 'expense');           // create a real transaction, default tab = expense
open(null, 'income', 'planned'); // create, starting on "Planned"
open(transaction.id);            // edit a transaction: pass its *id*, not a hydrated object
openPlanned(null, { type: 'income' }); // new plan
openPlanned(event);              // edit a plan (a presented planned event: PlannedEventPresenter::event)
openSeries(series.id);           // edit a whole repeating plan (fetches planned-series.edit-data)
openConfirm(event);              // record a plan as real, prefilled; date = the plan's date or today if later
```

The form's intents are `create`, `editTransaction`, `editPlanned`, `editSeries` and `confirmPlanned`; only `create`
shows the **Already happened / Planned** switch and the type tabs. Its rules (which route to submit to, the request
body, the future-date prompt, which buckets an expense can be paid from) live in the pure `resources/js/moneyMovement.js`,
tested with `npm run test:js`. An expense's saved bucket is fetched with `spend-options?include=<id>` and kept on load;
only a change the user makes (account or type) may drop it (`keepBucket`).

`useTransactionModal.js` holds its state in **module-scope `ref`s** (a plain shared-singleton pattern), not Vue's
`provide`/`inject`. If you need the same "any component can drive shared state" pattern elsewhere, copy this
composable's shape rather than reaching for `provide`/`inject` or a state library.

The shell fetches reference data **on demand** via plain `fetch()` against small non-Inertia JSON endpoints rather than
shared Inertia props: `TransactionController@formOptions` once (accounts with `id`, `name`, `currency`, `on_budget`, and
categories), `PlannedEventController@formOptions` (`planned-events.form-options`: frequencies, certainties, buckets) the
first time a plan is shown in each open (so a new bucket appears), and on every open the form fetches `editData`, `planned-series.edit-data` and the bucket
`spend-options`. These `fetch()` calls need a manual CSRF header (`resources/js/csrf.js`'s `withCsrfHeader()`, reading
the `XSRF-TOKEN` cookie) when they are not GETs, since they bypass Inertia's router. The CSV import wizard
(`Pages/Transactions/Import.vue`) uses the same `fetch()` + `withCsrfHeader()` pattern against its own `parse`/`store`
JSON endpoints.

## Projects
`Pages/Projects/Index.vue` (cards) and `Show.vue` (one project) get every number from `ProjectOverview` (never
recompute funded or budget left on the client). `Components/ProjectModal.vue` creates and edits a project and is the
only project form: the Projects pages and the Wish list both use it. A project's page links to
`/planning/wishlist?project=<id>`, which opens the Wish list's item form with that project chosen (`newItemProject`). The item form can make a project on the spot (`POST planning/projects/quick`, plain `fetch()` with `withCsrfHeader`, then it chooses it) and offers three ways to pay: ready to assign (or the project's bucket), an existing bucket, or a new bucket made for the item (`new_bucket` plus a fill rule and value).

## Planned rows

`Components/PlannedBand.vue` (the overdue and upcoming bands on the transactions list, from the deferred `planned`
prop, which Inertia reloads after every visit that returns the page, POST redirects included), the cash flow list and
the review inbox share `composables/usePlanActions.js`: **Confirm** (one click through `planned-events.realize` when
the plan has an account and a category, else `openConfirm`), **Adjust** (`openConfirm`), **Skip**, **Match** (link
the suggested transaction) and **Edit** (`openPlanned`). Projected rows are labelled "Projected, not in your balance"
and never mixed into real totals.

## Flowbite + Inertia navigation

Flowbite's dropdowns/modals/tooltips are wired via `data-*` attributes and only auto-init on `DOMContentLoaded`. Since Inertia swaps the page without a full reload, `resources/js/app.js` re-runs the initializer after every client-side navigation:

```js
router.on('navigate', () => initFlowbite());
```

For anything rendered from a `v-for` (dropdown per row, etc.), prefer a Vue-native `ref` + `v-show`/`v-if` over relying on Flowbite's DOM re-scan finding dynamically-added elements.

## Wayfinder action helpers

Action helpers are generated from each controller's full class name, so the import path includes its module (`@/actions/App/Modules/<Module>/Http/Controllers/<Name>Controller`). Moving or renaming a controller means regenerating (`vendor/bin/sail artisan wayfinder:generate --with-form`) and updating imports; `npm run build` fails loudly if one is stale. Never hardcode a URL. Import the generated helper and pass it to `useForm().submit()` / `router.get()`/`.delete()` / a plain `<a :href>`:

```js
import { store, update, destroy } from '@/actions/App/Modules/Planning/Http/Controllers/BudgetController';
import { index as accountsIndex } from '@/routes/accounts';

form.submit(editing.value ? update(editing.value.id) : store(), { preserveScroll: true });
router.delete(destroy.url(id));
<Link :href="accountsIndex.url()">Accounts</Link>
```

`resources/js/actions/` (controller-action helpers, used for `useForm().submit()`) and `resources/js/routes/` (route-name helpers, used for plain links/`router.get`) are both generated and gitignored — regenerate with `vendor/bin/sail artisan wayfinder:generate --with-form` after any route change, don't hand-edit them. Note the `--with-form` flag: without it, the generated actions lose their `.form` static property used in a couple of places (Wayfinder's Vite plugin also auto-regenerates on `npm run build`/`dev`, but without `--with-form`, so re-run the artisan command explicitly if you need the form helpers back after a build).

Where a controller method is bound to more than one route (e.g. `TransactionController@index` backs `transactions.index`, `incomes.index`, `expenses.index`, and `transfers.index` via `Route::defaults('type', ...)`), the generated `actions/` helper becomes a URI-keyed dictionary rather than a single callable — for these, use the named-route helper from `routes/` instead (e.g. `import { index as transactionsIndex } from '@/routes/transactions'`).
