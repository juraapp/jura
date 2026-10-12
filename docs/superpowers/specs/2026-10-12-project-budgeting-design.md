# Project Budgeting design (sub-projects 2 to 6)

Builds on `2026-10-12-modular-foundation-design.md`. All new backend code lives in the **Planning** module unless
stated; reports live in **Reporting**. Every table is user-scoped with `BelongsToUser`, money is `decimal(15,2)`
through `AsMoney`, currency is per row and never converted.

## Decisions (confirmed with the user)
| Topic | Decision |
|---|---|
| Buckets vs Budgets | Budgets evolve into buckets (one concept). Existing per-period budget rows collapse into **one bucket per category + currency + cycle type**, using the latest row's amount as the cycle limit. |
| Filling | Priority waterfall with per-bucket rules (fixed / percent / fill-to-target); leftover goes to the default bucket; per-event overrides. |
| Auto-fill | Automatic **only when at least one bucket has a fill rule**. No rules means nothing happens. Allocations can be adjusted or undone. |
| Planner | Suggest and compare: the app proposes orderings, the user can build their own, scenarios are shown side by side. |
| Bucket money | Buckets are virtual earmarks (an allocation ledger) over real accounts. A bucket may be restricted to **allowed accounts**; only income landing in those accounts fills it. |
| Series edits | Editing or deleting a repeating planned event asks **this one** or **this and all future**. Past occurrences are never touched. |
| Certainty | Three forecast views: **Conservative** (confirmed income only), **Expected** (confirmed + expected), **Optimistic** (all). Expenses always count in full. The planner defaults to Conservative. |
| Realizing a plan | A **Realize** action creates the real transaction from the plan (editable). A planned event also shows a **suggested match** when a similar real transaction exists; the user confirms. Never silent. |
| Purchasing a wish-list item | **Mark purchased** records the real expense and a linked spend from the item's bucket in one action; undoing removes both. |
| Vue strings | New pages use English literals like their siblings; server-side strings (flash, enum labels) are bilingual en/es. |
| Delivery | One branch (`feat/project-budgeting`), one PR (#1). |

## Concept map
- **Actual** data: `Transaction` (Ledger). **Projected** data: `PlannedEvent`, and bucket simulation (Planning).
  Projected rows never touch balances. Realizing a plan creates a real transaction and links back.
- **Funded** money: the `allocations` ledger. A bucket balance is the sum of its non-projected allocations.
- Category spending is tracked against a spending bucket's cycle limit exactly like the old budgets; it does **not**
  change funded balances. Purchases from savings buckets reduce funded balance through an explicit spend allocation.
- Out of scope in v1: currency conversion, planned expenses draining bucket funds, partial purchases, shared users.

## Sub-project 2: Planned cash flow
Tables
- `planned_event_series`: user_id, type (income|expense), description, amount, currency, account_id?, category_id?,
  certainty, frequency (`RecurringFrequency`), starts_on, ends_on?.
- `planned_events`: user_id, series_id?, type, description, amount, currency, date, account_id?, category_id?,
  certainty (`Certainty`: confirmed|expected|speculative), status (`PlannedEventStatus`: planned|realized|skipped),
  realized_transaction_id?, allocation_overrides (json)?, notes.

Behaviour
- Create one event, or a series that materializes occurrences up to its end date or 12 months ahead (max 120).
  A daily command extends open-ended series to 12 months ahead.
- `scope` = `this` | `future` on update/delete. `future` changes non-date fields of all later `planned` occurrences
  and trims/extends the series; the date can only be edited on `this`.
- Realize (amount/date/account editable; account required) records the transaction through
  `Ledger\Contracts\RecordsTransactions::record`, marks `realized`, stores the transaction id. Skip marks `skipped`.
- Suggested match: a real transaction of the same type and currency within ±5 days and ±2% of the amount that is not
  already linked. Link action confirms it.
- `CashFlowProjector`: per currency, opening balance = current net worth (`Ledger\Contracts\Balances`), then month
  buckets for the next N months with income, expense, net and closing balance for the chosen certainty view. Planned
  events dated in the past that are still `planned` count in the current month.
- Replaces `BudgetForecast`: rows migrate to planned expense events (anticipated -> expected, otherwise speculative);
  table, model, controller, policy, routes and page are removed. A budget's projected amount becomes the sum of planned
  expense events for its category, currency and period.
- Page `CashFlow/Index`: month selector, certainty view, event list grouped by month with Realize/Skip/Edit, add form
  with an optional "repeats" section, projection summary.

## Sub-project 3: Buckets
Tables
- `buckets` (replaces `budgets`): name, kind (spending|savings), currency, priority, category_id?, cycle
  (`BudgetPeriodType`?), cycle_start?, cap_amount?, fill_rule (`FillRule`: fixed|percent|fill_to_target)?, fill_value?,
  carry_over (`CarryOverPolicy`: carry|release|overflow), overflow_bucket_id?, allowed_account_ids (json)?, is_default.
- `allocations`: bucket_id, amount (signed), currency, date, source (`AllocationSource`: income|planned|transfer|carry|
  spend|adjustment), source_id?, description?, is_projected (false for stored rows).

Behaviour
- Pure `Waterfall`: given income amount, buckets in priority order (rule, remaining capacity) and overrides, returns the
  amount per bucket and the leftover. fixed = min(value, remaining); percent = value% of the income, capped by remaining;
  fill_to_target = min(target - balance, remaining).
- `IncomeAllocator` runs on a new Ledger event `IncomeRecorded` (and re-runs on update; `IncomeRemoved` deletes the
  income's allocations). Buckets whose allowed accounts exclude the income account are skipped.
- `BucketCycleService`, run daily: at cycle end the unspent balance follows the carry-over policy
  (carry: stays; release: moves to the default bucket; overflow: moves to the overflow bucket), then the cycle advances.
- Move money between buckets (two allocations, source transfer). Adjustments are manual allocations.
- Spending buckets keep the old budget behaviour (cap, spent, remaining, status, threshold notifications) through
  `BucketProgress` (renamed from `BudgetProgress`).
- Page `Buckets/Index` replaces `Budgets/Index`.

## Sub-project 4: Projects and wish list
- `projects`: name, kind (project|hobby|category|other), color, icon, notes.
- `wishlist_items`: project_id?, bucket_id?, name, price, currency, importance (1 to 5), desired_by?, allowed_account_ids?,
  status (wanted|planned|purchased|dropped), purchased_on?, purchased_transaction_id?, url?, notes.
- Purchase action: choose account (must be allowed by the item and its bucket) and expense category, records the real
  expense and a negative allocation (source spend) on the bucket; the pair is linked and can be undone together.
- Page `Wishlist/Index`: items grouped by project with funded progress and totals, project create/edit.

## Sub-project 5: Planner
- `ForecastEngine` (pure, DTO in/out): starts from current bucket balances, replays future income events (by certainty
  view) through the waterfall, applies cycle closes, then buys scenario lines in order at the earliest date funds are
  available (a line never precedes the previous one). Output: purchase date per line, delay, shortfall, leftover per
  bucket, funding timeline. An item with no bucket draws from the default bucket.
- `ScenarioSuggester`: candidate orderings (importance, cheapest first, earliest affordable, desired-by; permutations
  when 7 or fewer items), scored by importance-weighted delay plus a penalty for items not affordable in the horizon.
- Tables `scenarios` (name, certainty view, horizon) and `scenario_lines` (scenario_id, wishlist_item_id, position,
  not_before?).
- Page `Planner/Index`: pick items, see suggestions, save and compare scenarios side by side.

## Sub-project 6: Reporting
- Forecast report (12 months, three certainty views, history vs projection clearly distinguished), Buckets report
  (balances, allocation history, projected fills), Projects report (totals, funded %, estimated completion).
- Dashboard gains a "next 30 days" projection card.
- Cross-module access through new `Planning\Contracts` (`CashFlowForecast`, `BucketOverview`).

## Cross-cutting
- Docs, manual, sidebar entries and tests are part of each sub-project. Architecture boundaries stay strict.
- New Ledger contract surface: `RecordsTransactions::record`, `LedgerQuery::findSimilarTransactions`, events
  `IncomeRecorded` and `IncomeRemoved`.
