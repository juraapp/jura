# Jura

Jura is a personal finance management app for tracking accounts, income, expenses, budgets, savings goals, and recurring payments across multiple currencies — built with Laravel and Inertia + Vue.

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

## Tech stack

- **Backend**: PHP 8.5, Laravel 13
- **Frontend**: Inertia.js + Vue 3, Tailwind CSS, Flowbite, vue3-apexcharts
- **Database**: MariaDB
- **Cache/sessions**: Redis
- **PDF export**: spatie/laravel-pdf
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
./vendor/bin/sail npm run dev                    # run the Vite dev server (required for local frontend changes)
```

## Project structure

- `app/Services` — business logic (transaction ledger, balances, budgets, reports, insights, CSV import), kept out of controllers.
- `app/Http/Controllers` and `resources/js/Pages` — one Inertia controller/Vue page pair per feature, with validation extracted into `app/Http/Requests`.
- `resources/js/Components` and `resources/js/Layouts` — shared Vue components and the authenticated/guest layouts.
- `app/Policies` — per-model authorization (every write path is scoped to the authenticated user).
- `database/migrations` — schema, including the multi-currency support added on top of the base schema.
