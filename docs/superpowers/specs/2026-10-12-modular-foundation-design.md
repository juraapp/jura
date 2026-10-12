# Modular foundation (sub-project 1)

Part of the Project Budgeting roadmap. This sub-project changes **structure only**: no behaviour, URL, route
name, table or UI change. The 128-test suite must pass unchanged after every step.

## Goal
Turn the type-folder layout (`app/Models`, `app/Services`, ...) into domain modules under `app/Modules/`
with enforced boundaries, so the Planning domain (planned events, buckets, wish list, planner) can be added
without deepening the `Transaction` hub coupling.

## Modules
| Module | Owns |
|---|---|
| `Shared` (kernel) | `Money`, `AsMoney`, `BelongsToUser`, base `Controller`, shared enums that cross modules |
| `Identity` | `User`, auth controllers/requests, profile, preferences, `HandleInertiaRequests` |
| `Ledger` | `Account`, `Category`, `Transaction`, `TransactionService`, `BalanceCalculator`, CSV import, `TransactionsMutated`, `InvalidateBalanceCache`, their policies/requests/controllers/factories |
| `Planning` | `Budget`, `BudgetForecast`, `SavingsGoal`, `SavingsGoalContribution`, `RecurringTransaction`, services, `GenerateDueRecurringTransactions`, policies/requests/controllers/factories |
| `Reporting` | `ReportService`, `InsightService`, `DashboardController`, `ReportController`, PDF/CSV controllers |
| `Notifications` | the four notification classes, `CheckBudgetThreshold`, `CheckLowAccountBalance`, `NotificationController`, the two `Notify*` commands |

Layout per module: `Models/ Services/ Http/{Controllers,Requests}/ Policies/ Enums/ Events/ Listeners/
Contracts/ Database/Factories/` (only the folders that have content), namespace `App\Modules\<Module>\...`.

## Boundary rules
1. A module may import another module's `Contracts\*`, `Events\*`, `Enums\*` and `Shared`. It may not import
   another module's `Models`, `Services`, `Http` or `Policies`.
   - Exception: models may relate to models of a module they depend on (`Planning\Budget` -> `Ledger\Category`).
     Dependencies point one way: `Planning`, `Reporting` and `Notifications` may depend on `Ledger`;
     `Ledger` depends on nothing but `Shared`. `Notifications` also depends on `Planning`.
2. Cross-module reads use contracts returning plain data (arrays/DTOs), for example
   `Ledger\Contracts\LedgerQuery` (cash flow by category/account/period, account balances, net worth).
   `Reporting` and `Planning` call it instead of querying `Transaction` directly.
3. Cross-module writes use events. `RecurringTransactionService` creates transactions through
   `Ledger\Contracts\RecordsTransactions` (a thin interface over `TransactionService`).
4. Contracts are bound in each module's service provider, registered in `bootstrap/providers.php`.
5. An architecture test (`tests/Architecture/ModuleBoundariesTest.php`) scans `use` statements and fails on
   violations. It starts with an explicit allowlist of known violations, and the allowlist may only shrink.

## What does not move
- URLs, route names and route files (`routes/web.php` stays; controllers are re-imported). Wayfinder
  output is unaffected.
- Database tables and migration files. Ownership of each table is documented in `docs/domain-model.md`.
- `resources/js`. Page folders already follow domains.
- Tests stay in `tests/Feature` (they are HTTP-level). Add `tests/Architecture`.

## Known traps to handle during the move
- **Policy auto-discovery:** moved models need their policy found. Verify with a test, otherwise register with
  `Gate::policy` in the module provider.
- **Factories:** `HasFactory` guesses `Database\Factories\<Model>Factory`. Moved models need
  `#[UseFactory]` or `newFactory()`.
- **Stored class names:** the `notifications.type` column and any queued/serialised job payloads hold old class
  names. Ship a one-off data migration for notification types, and note queue flush in the deploy notes.
- **Config references:** `config/auth.php` user model, `AppServiceProvider` event wiring, the scheduler in
  `routes/console.php`, `#[Description]` command registration.
- **Imports churn:** most diffs are `use` lines. Do the move with scripted namespace rewrites and review
  `git diff -M` for rename detection; keep each module's move one commit.

## Steps (each ends with a green suite and its own commit)
1. Shared kernel (`Money`, `AsMoney`, `BelongsToUser`, `Controller`). Add the architecture test with the
   allowlist containing everything not yet moved.
2. Identity.
3. Ledger, including `LedgerQuery` and `RecordsTransactions` contracts.
4. Planning, switching its direct `Transaction` queries to `LedgerQuery`.
5. Reporting, switching `ReportService` and `DashboardController` to `LedgerQuery`; split `ReportService`
   by read concern if it stays above ~250 lines.
6. Notifications.
7. Shrink the allowlist to empty. Update `docs/architecture.md`, `docs/domain-model.md`,
   `docs/testing-conventions.md`, `CLAUDE.md` project section, and record the boundary rules with `record-rule`.

## Success criteria
- Full suite green at every step, same test count or higher.
- Architecture test passes with an empty allowlist.
- No change to any URL, route name, table or rendered page (spot-check dashboard, transactions, budgets,
  reports in the browser).
- Docs and `CLAUDE.md` describe the new layout.
