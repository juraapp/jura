# Jura

*[Leer en español](README.es.md)*

Jura is a personal finance management app for tracking accounts, income, expenses, budgets, savings goals, and recurring payments across multiple currencies — built with Laravel, Livewire, and Volt.

## Features

- **Accounts** — banks, digital wallets, and cash, each with its own currency (e.g. COP and USD side by side, never mixed together in totals).
- **Transactions** — income, expenses, and transfers between accounts, with categories and payment methods.
- **CSV import** — bulk-import income/expense transactions from a spreadsheet, with per-row validation and duplicate detection.
- **Budgets** — per-category spending limits (monthly or yearly), scoped to a currency, with threshold alerts.
- **Savings goals** — target amounts with optional deadlines, contribution tracking, and a suggested monthly savings pace.
- **Recurring transactions** — auto-generate transactions on a schedule (weekly, biweekly, monthly, yearly), with upcoming-payment reminders.
- **Dashboard & reports** — monthly, annual, and custom-range reports with category/account breakdowns, PDF/CSV export, and automatic spending insights.
- **Notifications** — low balance, budget threshold, upcoming recurring payment, and behind-schedule savings goal alerts.
- **Multi-currency aware** — every aggregate (net worth, reports, budgets) is grouped by currency instead of summing different currencies together.
- **Localization** — English by default, with Spanish available as a secondary language via `lang/es.json`.

## Tech stack

- **Backend**: PHP 8.3, Laravel 13
- **Frontend**: Livewire 3 + Volt (single-file components), Tailwind CSS, Alpine.js, ApexCharts
- **Database**: MariaDB
- **Cache/sessions**: Redis
- **PDF export**: barryvdh/laravel-dompdf
- **Local environment**: Laravel Sail (Docker)

## Getting started

Requires Docker.

```bash
composer install
cp .env.example .env
php artisan sail:install # if Sail isn't already configured
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

The app runs at `http://localhost`. The seeder creates a test user:

- **Email**: `test@example.com`
- **Password**: `password`

## Useful commands

```bash
./vendor/bin/sail artisan migrate:fresh --seed   # reset the database
./vendor/bin/sail artisan test                   # run the test suite
./vendor/bin/sail npm run build                  # build front-end assets for production
```

## Project structure

- `app/Services` — business logic (transaction ledger, balances, budgets, reports, insights, CSV import), kept out of Livewire components.
- `app/Livewire` and `resources/views/livewire` — Livewire class components and Volt single-file components, one per feature.
- `app/Policies` — per-model authorization (every write path is scoped to the authenticated user).
- `database/migrations` — schema, including the multi-currency support added on top of the base schema.

## Localization

The app locale (`APP_LOCALE`, default `en`) drives translated UI strings via `lang/es.json`. Source strings are written in English (`__('English text')`); Spanish is fully supported as a secondary language by adding a matching entry to `lang/es.json`. Independently, each user can set a `locale` preference under **Preferences** (English/Spanish) that only affects number and date formatting (e.g. `1,234.56` vs `1.234,56`), not which language the UI text is translated into.
