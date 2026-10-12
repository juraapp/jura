# Domain Model

## Models

Models live in modules: `Ledger` (Account, Category, Transaction), `Planning` (Bucket, Allocation, Project, WishlistItem, Scenario, PlannedEvent, PlannedEventSeries, SavingsGoal, SavingsGoalContribution, RecurringTransaction), `Identity` (User). See `architecture.md` for the boundary rules.

| Model | Notes |
|---|---|
| `User` | `currency_default`, `locale`, `timezone` drive formatting app-wide (see `preferences`). |
| `Account` | Bank/wallet/cash/credit-card/savings. `initial_balance` + the transaction ledger derive the live balance — balance is never a stored column (see `BalanceCalculator`). Optional `yield_rate` (percent per year) + `yield_compounding` (daily/monthly/yearly) model interest on real money only; the cash-flow projection simulates it with the pure `Planning/Support/YieldSimulator` (never stored, never a transaction). Soft-deletes; deleting an account with transactions archives it (`is_active = false`) instead. |
| `Category` | Income or expense. `user_id` nullable — `null` means a system category (seeded, shared by every user). Uses a **custom** `visibleToUser` global scope (owner OR `user_id IS NULL`), not `BelongsToUser` — see below. Soft-deletes; system categories and categories with transactions can't be deleted/edited (`Category::isEditable()`). |
| `BucketCycle` | One cycle of a bucket. The open one only carries a per-cycle `cap_override`; a closed one is an immutable snapshot (`opening`, `filled`, `moved_in`, `moved_out`, `spent`, `released`, `closing`, `cap`) written by `BucketCycleService`. |
| `Bucket` | A virtual earmark of money (`spending` or `savings`), replacing `Budget`. Spending buckets track a `category` against a per-`cycle` `cap_amount` (monthly/yearly) exactly like budgets did; savings buckets hold funded money with an optional target. Optional `fill_rule` (fixed/percent/of_remaining/fill_to_target; `fixed` is per cycle when the bucket has a cycle, counting income already received in it via `BucketLedger::receivedInCycle`; `of_remaining` is a share of the still-unclaimed income) + `fill_value` claim income in `priority` order; `carry_over` (carry/release/overflow) decides what happens to the leftover when a cycle ends; `cycle_goal` (savings only: what it aims to receive each cycle), `cycle` (daily/weekly/monthly/yearly) + `cycle_every` + optional `cycle_anchor` define the repeating window (pure `Support/CyclePeriod`; month-end days clamp); `allowed_account_ids` restricts which accounts' income can fill it; one `is_default` bucket per currency catches unclaimed income. Unique per user/category/currency/cycle. |
| `Allocation` | One signed line of the virtual money ledger (`income`, `planned`, `transfer`, `carry`, `spend`, `adjustment`). A bucket's funded balance is the sum of its allocations; for a category-linked spending bucket, *available* additionally subtracts real spending since `tracking_started_on`. |
| `SavingsGoal` | Target amount + optional target date. `status`: active/completed/archived. Soft-deletes. Has many `SavingsGoalContribution`; recording a contribution that reaches the target auto-flips status to completed. |
| `SavingsGoalContribution` | Belongs to a `SavingsGoal`; optionally linked to the `Transaction` it came from (`linked_transaction_id`, nullable). |
| `RecurringTransaction` | Template for auto-generated transactions (weekly/biweekly/monthly/yearly). `is_active` toggles without deleting. |
| `Project` / `WishlistItem` | A `Project` (project/hobby/category/other) groups wish-list items. A `WishlistItem` has a price, `importance` 1-5, optional `desired_by`, `bucket_id` (a savings bucket of the same currency), `allowed_account_ids`, and a `status` (wanted/purchased/dropped). Purchasing records a real expense through `RecordsTransactions::record` plus a negative `spend` `Allocation` (capped to the bucket's available funds) and stores both ids so the purchase can be undone together (`WishlistService`). |
| `Scenario` | A saved purchase order for the planner: a name, currency, `forecast_view` (conservative/expected/optimistic), `horizon_months` (12/24/36) and ordered `ScenarioLine`s (`wishlist_item_id`, `position`, optional `not_before`). Results are never stored; they are recomputed on demand. |
| `PlannedEvent` | A projected income or expense on a future date (`planned`/`realized`/`skipped`) with a `certainty` (confirmed/expected/speculative). **Never touches balances.** Realizing one records a real `Transaction` through `Ledger\Contracts\RecordsTransactions` and stores its id in `realized_transaction_id`. Optionally part of a `PlannedEventSeries` (a repeating rule that materializes occurrences up to 12 months ahead; extended daily by `planning:extend-series`). Deleting one occurrence hard-deletes the row and remembers its date in the series' `excluded_dates`, so extending never recreates it; a `skipped` event is a visible user action that can be reopened. Replaces the old `BudgetForecast`. |
| `Transaction` | `type`: income / expense / transfer_out / transfer_in. A transfer is always **two rows** sharing a `transfer_group_id` (see `TransactionService::createTransfer`) — never a single row with a "to account" column. `category_id` is null only for transfer rows. Soft-deletes. |

Cross-module events from `Ledger`: `TransactionsMutated`, `IncomeRecorded`, `IncomeRemoved`.

All models except `Category` (and the join-only `SavingsGoalContribution`) use the `App\Modules\Shared\Concerns\BelongsToUser` trait.

## `BelongsToUser`

```php
trait BelongsToUser
{
    protected static function bootBelongsToUser(): void
    {
        static::addGlobalScope('user', fn (Builder $builder) =>
            Auth::check() && $builder->where($builder->getModel()->getTable().'.user_id', Auth::id()));

        static::creating(fn ($model) => $model->user_id ??= Auth::id());
    }
}
```

Every query against a `BelongsToUser` model is transparently scoped to the authenticated user — including **implicit route-model binding**. This has a consequence that shows up throughout the controllers and their tests: **an authenticated user requesting another user's resource by ID gets a 404, not a 403** — the row is invisible to the query before a Policy ever runs, not merely forbidden. Only `Category`'s custom scope (owner OR system) and cases where a Form Request's `exists:` rule bypasses Eloquent scopes entirely (see below) deviate from this.

Use `Model::forUser($user)` (also from the trait) to query outside a request context (jobs, console commands) where `Auth::check()` is false.

## Validation must re-scope `exists:` rules

`exists:accounts,id` / `exists:categories,id` in a Form Request checks **only that a row exists**, not that it belongs to the current user — Laravel's `exists` rule runs a raw query, bypassing the `BelongsToUser` global scope entirely. Every Form Request that accepts an `account_id` or `category_id` foreign key scopes it explicitly:

```php
'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('user_id', $this->user()->id)],
'category_id' => ['required', 'integer', Rule::exists('categories', 'id')
    ->where(fn ($query) => $query->where('user_id', $this->user()->id)->orWhereNull('user_id'))],
```

(Categories additionally allow `user_id IS NULL` — system categories are valid for everyone.) If you add a new Form Request with a foreign key onto a `BelongsToUser` model, scope it the same way — a bare `exists:table,id` is a cross-tenant data leak, not just a style nit. This exact gap existed in the original Livewire app and was only caught while writing Phase 8's feature tests (see `testing-conventions.md`).

## Key services (inside their modules)

- **`TransactionService`** (Ledger) — the only code path allowed to write a `Transaction`. Owns transfer double-entry (`createTransfer`/`updateTransfer`/`delete` all keep both legs of a transfer in sync or delete them together) and dispatches `TransactionsMutated` so caches invalidate.
- **`BalanceCalculator`** (Ledger) — derives account balances and net worth from `initial_balance` + the ledger, cached (Redis, 1h TTL, keyed per-account/per-user) because it's read on nearly every page. Cache is invalidated via `forgetAccount()`, called after any mutation that could change a balance.
- **`PlannedEventService`** (Planning) — creates events/series, edits and deletes with `this`/`future` scope, realizes, skips and links plans, and suggests matching real transactions. `CashFlowProjector` (Planning) projects balances per month and currency for a `ForecastView` (conservative = confirmed income only, expected = + expected, optimistic = all; expenses always count).
- **`PlannerService`** (Planning) — builds engine input from real data (bucket `available` funds, open planned incomes filtered by the `ForecastView`, wish-list items with `blocked` = item and bucket accounts do not overlap) and presents results. The maths lives in pure classes under `Planning/Support/Forecast`: `ForecastEngine` replays incomes through the `Waterfall`, applies end-of-cycle carry-over and buys scenario lines strictly in order at the earliest date their bucket can pay; `ScenarioSuggester` scores orderings (importance-weighted waiting days, a penalty for items not bought within the horizon and for lateness) and tries every permutation up to 6 items, then local search.
- **`PlanningReportService`** (Reporting) — the only place where recorded history (`ReportService`) and projections (Planning contracts) meet; keeps them under separate keys so a page never mixes real and projected numbers.
- **`BucketService`** (Planning) — limit tracking for spending buckets (`progress()`: spent/remaining/percentage/status on_track/near_limit/exceeded for the current cycle). `BucketLedger` is the allocation ledger (balance, available, credit, move, default bucket); `BucketManager` saves/deletes buckets keeping the invariants; `IncomeAllocator` runs the pure `Waterfall` when a Ledger `IncomeRecorded` event fires (only if some bucket has a rule; planned incomes may carry explicit per-bucket overrides); `BucketCycleService` (daily `planning:close-bucket-cycles`) closes every finished cycle in order (catching up on missed ones), writes the `BucketCycle` snapshot and applies carry-over; `CycleProjector` computes the *provisional* fill of the current cycle from open planned income (never stored) and the expected overflow at its end; `Bucket::effectiveCap()` honours a limit set for one cycle.
- **`ReportService`** (Reporting) — the aggregation layer behind the dashboard and the three report pages (monthly/annual/custom): period totals, expense-by-category, expense-by-account, net-worth evolution, etc. Every aggregate is a `Collection` **keyed by currency** — amounts in different currencies are never summed together.
- **`InsightService`** (Reporting) — turns `ReportService`'s numbers into short, translated sentences ("You spent 12% more than last month"). Every phrase is a parametrized `__()` call over real aggregates, never freeform text.
- **`TransactionImportService`** (Ledger) — parses/validates/imports a CSV of income/expense rows. Never writes a `Transaction` directly; hands validated rows to `TransactionService` so import-created transactions go through the exact same path as manually-created ones.
- **`RecurringTransactionService`** (Planning) — generates due `Transaction`s from active `RecurringTransaction` templates (see the `notify:*` console commands).

## Money

`App\Modules\Shared\Support\Money` is an immutable, bcmath-backed value object — amounts are never manipulated as PHP floats. `App\Modules\Shared\Casts\AsMoney` is the Eloquent cast used on every money column (`amount`, `initial_balance`, `target_amount`, ...): reading the attribute returns a `Money`, writing accepts a `Money`, numeric string, or float. **Controllers must call `->toFloat()` (or `->toDecimalString()`) before putting a `Money` into an Inertia prop or JSON response** — Inertia doesn't know how to serialize it.
