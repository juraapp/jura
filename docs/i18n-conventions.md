# i18n Conventions

## Display formats
Language (`locale`) decides the words; the separate display preferences (`date_format`, `time_format`, separators,
`fraction_digits`, `currency_display`, `currency_position`, all "auto" by default) decide how dates and numbers
look. Do not format numbers or dates by hand: use `useFormat()` in Vue and `$user->formatter()` (`UserFormat`) in PHP.

## Structure

`lang/` is directory-based PHP arrays, not the old flat `lang/es.json`:

```
lang/en/   accounts.php  buckets.php  categories.php  enums.php  insights.php
           messages.php  notifications.php  pdf.php  preferences.php
           planned-events.php  planner.php  recurring-transactions.php  savings-goals.php  transactions.php  wishlist.php
lang/es/   (mirrors lang/en/ exactly, same keys)
```

- One file per domain module for flash/action strings (`__('budgets.saved')`, `__('categories.has_transactions')`), following the flat-array style in e.g. `lang/en/accounts.php`.
- `enums.php` — `label()` methods on every backend enum (`AccountType`, `CategoryType`, `TransactionType`, `PaymentMethod`, `RecurringFrequency`, `BudgetPeriodType`, `SavingsGoalStatus`) resolve through `__('enums.{enum_snake_case}.{value}')`.
- `messages.php` — cross-cutting strings not owned by one domain (CSV column headers under `messages.csv.*`, `TransactionImportService`'s error messages under `messages.import.*`).
- `insights.php` — every templated sentence `InsightService` produces.
- `.env`: `APP_LOCALE=en`, `APP_FALLBACK_LOCALE=en` — English is the source of truth; Spanish is the translation, not the default.

`APP_LOCALE` is the default for guests. For signed-in users the `SetUserLocale` middleware (Shared, registered before `HandleInertiaRequests`) calls `App::setLocale()` with the saved `locale` preference, so server strings and the shared `i18n` props follow it. Vue pages still use English literals (see below), so changing the language only affects server-side text until those are migrated to `$t()`.

## Server → client bridge

`HandleInertiaRequests::share()` puts the active language's strings on every Inertia response through
`UiMessages::for($locale)`: `lang/{locale}/messages.php` plus every file in `lang/{locale}/ui/` (one file per area,
exposed as `ui.<file>.<key>`). English fills any key missing in Spanish. `resources/js/app.js` boots vue-i18n from it.

## Translating Vue pages

Vue pages and components use `$t('ui.<area>.<key>')` in templates and `const { t } = useI18n()` in scripts.

- Add the key to both `lang/en/ui/<area>.php` and `lang/es/ui/<area>.php` (`common.php` for shared words like Cancel,
  `shell.php` for navigation, topbar, notifications and bulk bars, one file per page area otherwise).
- Placeholders use vue-i18n braces (`'Delete {label}?'`), not Laravel's `:label`. Avoid `@`, `|` and `{`/`}` as literal text
  in a value (they are message syntax); plurals use `'one item | {count} items'` with `t(key, { count }, count)`.
- Spanish says "balde" for bucket.
- `tests/Feature/UiTranslationsTest.php` fails when `en` and `es` differ in keys or a page uses a key that does not exist.
  It cannot see English text left hard-coded in a template, so reviewing a page means grepping it for literals.
- Pages not yet migrated still show English literals; see the open `i18n:` issues for what remains.
