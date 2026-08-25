# Domain Model

## Models

| Model | Notes |
|---|---|
| `User` | `currency_default`, `locale`, `timezone` drive formatting app-wide (see `preferences`). |
| `Account` | Bank/wallet/cash/credit-card/savings. `initial_balance` + the transaction ledger derive the live balance — balance is never a stored column (see `BalanceCalculator`). Soft-deletes; deleting an account with transactions archives it (`is_active = false`) instead. |
| `Category` | Income or expense. `user_id` nullable — `null` means a system category (seeded, shared by every user). Uses a **custom** `visibleToUser` global scope (owner OR `user_id IS NULL`), not `BelongsToUser` — see below. Soft-deletes; system categories and categories with transactions can't be deleted/edited (`Category::isEditable()`). |
| `Budget` | Per-category spending limit, scoped to a `currency` and `period_type` (monthly/yearly), for a specific `period_start`. Unique on `(user_id, category_id, currency, period_type, period_start)` — recreating a budget for the same period updates the amount (`updateOrCreate`) instead of duplicating. |
| `SavingsGoal` | Target amount + optional target date. `status`: active/completed/archived. Soft-deletes. Has many `SavingsGoalContribution`; recording a contribution that reaches the target auto-flips status to completed. |
| `SavingsGoalContribution` | Belongs to a `SavingsGoal`; optionally linked to the `Transaction` it came from (`linked_transaction_id`, nullable). |
| `RecurringTransaction` | Template for auto-generated transactions (weekly/biweekly/monthly/yearly). `is_active` toggles without deleting. |
| `Transaction` | `type`: income / expense / transfer_out / transfer_in. A transfer is always **two rows** sharing a `transfer_group_id` (see `TransactionService::createTransfer`) — never a single row with a "to account" column. `category_id` is null only for transfer rows. Soft-deletes. |

All models except `Category` (and the join-only `SavingsGoalContribution`) use the `App\Models\Concerns\BelongsToUser` trait.

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

## Key services (`app/Services/`)

- **`TransactionService`** — the only code path allowed to write a `Transaction`. Owns transfer double-entry (`createTransfer`/`updateTransfer`/`delete` all keep both legs of a transfer in sync or delete them together) and dispatches `TransactionsMutated` so caches invalidate.
- **`BalanceCalculator`** — derives account balances and net worth from `initial_balance` + the ledger, cached (Redis, 1h TTL, keyed per-account/per-user) because it's read on nearly every page. Cache is invalidated via `forgetAccount()`, called after any mutation that could change a balance.
- **`BudgetService`** — computes a budget's `spent`/`remaining`/`percentage`/`status` (on_track/near_limit/exceeded) for its current period.
- **`ReportService`** — the aggregation layer behind the dashboard and the three report pages (monthly/annual/custom): period totals, expense-by-category, expense-by-account, net-worth evolution, etc. Every aggregate is a `Collection` **keyed by currency** — amounts in different currencies are never summed together.
- **`InsightService`** — turns `ReportService`'s numbers into short, translated sentences ("You spent 12% more than last month"). Every phrase is a parametrized `__()` call over real aggregates, never freeform text.
- **`TransactionImportService`** — parses/validates/imports a CSV of income/expense rows. Never writes a `Transaction` directly; hands validated rows to `TransactionService` so import-created transactions go through the exact same path as manually-created ones.
- **`RecurringTransactionService`** — generates due `Transaction`s from active `RecurringTransaction` templates (see the `notify:*` console commands).

## Money

`App\Support\Money` is an immutable, bcmath-backed value object — amounts are never manipulated as PHP floats. `App\Casts\AsMoney` is the Eloquent cast used on every money column (`amount`, `initial_balance`, `target_amount`, ...): reading the attribute returns a `Money`, writing accepts a `Money`, numeric string, or float. **Controllers must call `->toFloat()` (or `->toDecimalString()`) before putting a `Money` into an Inertia prop or JSON response** — Inertia doesn't know how to serialize it.
