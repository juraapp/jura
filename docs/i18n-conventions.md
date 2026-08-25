# i18n Conventions

## Structure

`lang/` is directory-based PHP arrays, not the old flat `lang/es.json`:

```
lang/en/   accounts.php  budgets.php  categories.php  enums.php  insights.php
           messages.php  notifications.php  pdf.php  preferences.php
           recurring-transactions.php  savings-goals.php  transactions.php
lang/es/   (mirrors lang/en/ exactly, same keys)
```

- One file per domain module for flash/action strings (`__('budgets.saved')`, `__('categories.has_transactions')`), following the flat-array style in e.g. `lang/en/accounts.php`.
- `enums.php` — `label()` methods on every backend enum (`AccountType`, `CategoryType`, `TransactionType`, `PaymentMethod`, `RecurringFrequency`, `BudgetPeriodType`, `SavingsGoalStatus`) resolve through `__('enums.{enum_snake_case}.{value}')`.
- `messages.php` — cross-cutting strings not owned by one domain (CSV column headers under `messages.csv.*`, `TransactionImportService`'s error messages under `messages.import.*`).
- `insights.php` — every templated sentence `InsightService` produces.
- `.env`: `APP_LOCALE=en`, `APP_FALLBACK_LOCALE=en` — English is the source of truth; Spanish is the translation, not the default.

`APP_LOCALE` is a static, app-wide default — there is currently no per-request locale switch wired to `Auth::user()->locale`; a user's saved locale preference (`settings/preferences`) is persisted but nothing sets `app()->setLocale()` from it. If you build a locale switcher, that's the missing piece — a middleware calling `App::setLocale($request->user()->locale)` after auth.

## Server → client bridge

`HandleInertiaRequests::share()` puts Laravel's translations directly on every Inertia response:

```php
'i18n' => [
    'locale' => app()->getLocale(),
    'messages' => Lang::get('messages'),
],
```

`resources/js/app.js` boots vue-i18n from this shared prop — `lang/{en,es}/messages.php` is the **single source of truth** for both server- and client-rendered strings; there's no separate JSON translation file to keep in sync:

```js
const { locale, messages } = props.initialPage.props.i18n;
const i18n = createI18n({ legacy: false, locale, fallbackLocale: 'en', messages: { [locale]: messages } });
```

Validation errors arrive already-localized via Inertia's `usePage().props.errors` — no separate client-side handling needed.

## Current state: Vue pages are English-hardcoded

**None of the Vue pages actually call `$t()`/`useI18n()`.** The vue-i18n bootstrap above is wired and functional, and it's fed from `messages.php`, but every `Pages/*.vue` and `Components/*.vue` built during the Inertia migration writes its UI strings as plain English literals directly in the template (`<h2>Budgets</h2>`, not `<h2>{{ $t('budgets.title') }}</h2>`). Only the **Laravel-side** strings (flash messages via `__()`, enum labels, PDF/CSV content) are genuinely bilingual right now.

If you're adding a new user-facing string in a Vue component and want it translated:

1. Add the key to both `lang/en/messages.php` and `lang/es/messages.php` (or a more specific domain file if one fits).
2. Use `$t('your.key')` in the template — `useI18n()` is already globally installed (`app.use(i18n)` in `app.js`), no per-component setup needed.
3. Existing hardcoded strings are not required to be migrated as a prerequisite — just don't hardcode a *new* string if you're touching a component seriously, and prefer converting the page's other strings to `$t()` while you're in there rather than mixing hardcoded and translated text.

Grep to check for it: `grep -rL '\$t(' resources/js/Pages` lists every Page file with zero translation calls — currently that's all of them.
