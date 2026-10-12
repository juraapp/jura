# Modular Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move `app/` from type folders into six domain modules (`Shared`, `Identity`, `Ledger`, `Planning`, `Reporting`, `Notifications`) with enforced boundaries, without changing any URL, route name, table or rendered page.

**Architecture:** Mechanical namespace moves done by a scripted tool (one module per commit, suite green each time), then the few cross-module couplings that remain are replaced by small contracts (`Balances`, `LedgerQuery`, `RecordsTransactions`, `BudgetProgress`). A plain-PHPUnit architecture test scans `use`/FQN references and fails on boundary violations.

**Tech Stack:** Laravel 13, PHP 8.5 (Sail), PHPUnit 12, Python 3 (move tool, kept outside the repo), Pint.

**Spec:** `docs/superpowers/specs/2026-10-12-modular-foundation-design.md`. Task 0 amends it with the refinements below.

## Conventions for every task
- All PHP/artisan commands run through Sail. Test command (DB credentials are in `.env`): `vendor/bin/sail artisan test --compact`. Baseline before this plan: **128 passed**.
- After every move: `vendor/bin/sail bin pint --dirty --format agent`, then `vendor/bin/sail artisan optimize:clear`, then the full suite.
- Commits: Conventional Commits, atomic, **no `Co-Authored-By` trailer** (`.ai/rules/general.md`).
- Scratch dir for the tool (not committed): `/tmp/claude-1001/-home-jhdg-Projects-jura/d702a7b3-fbaf-4cd3-98de-c14a50e91bd7/scratchpad`, referred to below as `$SCRATCH`.
- Branch: `feat/project-budgeting`.

## Refinements to the spec (applied in Task 0)
1. **Factories stay in `database/factories/`.** Moved models are found by two global resolvers in `SharedServiceProvider`, so no per-model attributes are needed.
2. **`HandleInertiaRequests` goes to `Shared`** (cross-cutting; it uses the `Balances` contract), not `Identity`.
3. **Read-side modules (`Reporting`, `Notifications`) may import other modules' `Models`.** They only read. Write-side modules (`Ledger`, `Planning`, `Identity`) reach other modules through `Contracts`/`Events`/`Enums`; their own `Models/` may reference other modules' models for relationships.
4. **Contracts are purpose-built, not generic query builders:** `Ledger\Contracts\{Balances, LedgerQuery, RecordsTransactions}` and `Planning\Contracts\BudgetProgress`.
5. The boundary test starts strict, with no allowlist. Each task fixes its own violations in the same commit.
6. Moving listeners/commands out of `app/Listeners` and `app/Console/Commands` removes Laravel's auto-discovery of them, so providers register them explicitly (see Task 7). Verify with `event:list` and `list` before/after.

## File structure (end state)
```
app/Modules/
  Shared/       SharedServiceProvider.php, Support/Money.php, Casts/AsMoney.php, Concerns/BelongsToUser.php,
                Http/Controllers/Controller.php, Http/Middleware/HandleInertiaRequests.php
  Identity/     Models/User.php, Http/{Controllers(Auth/*, Profile, Preference), Requests}
  Ledger/       LedgerServiceProvider.php, Models/{Account,Category,Transaction}, Enums/*, Services/{TransactionService,
                BalanceCalculator,TransactionImportService,EloquentLedgerQuery}, Contracts/{Balances,LedgerQuery,RecordsTransactions},
                Events/TransactionsMutated, Listeners/InvalidateBalanceCache, Exceptions/*, Policies/*, Http/*
  Planning/     PlanningServiceProvider.php, Models/*, Enums/*, Services/{BudgetService,BudgetForecastService,
                RecurringTransactionService}, Contracts/BudgetProgress, Jobs/*, Policies/*, Http/*
  Reporting/    Services/{ReportService,InsightService}, Http/Controllers/{Dashboard,Report,Reports/*}
  Notifications/ NotificationsServiceProvider.php, Notifications/*, Listeners/{CheckBudgetThreshold,CheckLowAccountBalance},
                Console/Commands/*, Http/Controllers/NotificationController.php
app/Providers/AppServiceProvider.php   (Vite prefetch only)
app/View/Components/*                  (unchanged Breeze leftovers)
tests/Architecture/ModuleBoundariesTest.php
tests/Feature/ModuleContractsTest.php
```

---

### Task 0: Amend the spec and verify the baseline

**Files:**
- Modify: `docs/superpowers/specs/2026-10-12-modular-foundation-design.md`

- [ ] **Step 1: Confirm the baseline is green**

Run: `vendor/bin/sail artisan test --compact`
Expected: `128 passed`.

- [ ] **Step 2: Record event and command registrations before the move**

Run: `vendor/bin/sail artisan event:list > $SCRATCH/events.before.txt; vendor/bin/sail artisan list --raw | awk '{print $1}' | sort > $SCRATCH/commands.before.txt; wc -l $SCRATCH/events.before.txt $SCRATCH/commands.before.txt`
Expected: two non-empty files. They are compared again in Task 7.

- [ ] **Step 3: Apply the refinements to the spec**

In the spec, replace the module table row for `Shared` with `Money`, `AsMoney`, `BelongsToUser`, base `Controller`, `HandleInertiaRequests`, `SharedServiceProvider`; remove `HandleInertiaRequests` from the `Identity` row; replace "Boundary rules" items 1–3 with:

```markdown
1. Always allowed across modules: `Shared`, another module's `Contracts\*`, `Events\*`, `Enums\*`, and `Identity\Models\User`.
2. Another module's `Models\*` may be referenced only from (a) files under a `Models/` directory (relationships), or (b) the read-side modules `Reporting` and `Notifications`, which only read.
3. Everything else (`Services`, `Http`, `Policies`, `Jobs`, ...) of another module is off limits. Cross-module reads/writes go through contracts: `Ledger\Contracts\{Balances, LedgerQuery, RecordsTransactions}`, `Planning\Contracts\BudgetProgress`.
```

Replace the "What does not move" bullet list item about factories by: "Factories stay in `database/factories/`; `SharedServiceProvider` resolves factory<->model names across modules." Replace the allowlist sentence in item 5 with: "The test is strict from the start, with no allowlist."

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/specs/2026-10-12-modular-foundation-design.md
git commit -m "docs(architecture): refine modular foundation spec boundaries"
```

---

### Task 1: Build the move tool (outside the repo)

**Files:**
- Create: `$SCRATCH/move_module.py` (not committed)

- [ ] **Step 1: Write the tool**

```python
#!/usr/bin/env python3
"""Usage: move_module.py MOVES_FILE   (each line: "old/path.php new/path.php", relative to repo root)

git-mv files, rewrite their namespace, rewrite every reference to the moved
classes (FQNs and double-backslash strings) across app/tests/database/routes/
config/bootstrap, and add `use` lines where a class previously relied on being
in the same namespace as a class that is now elsewhere.
"""
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path('/home/jhdg/Projects/jura')
SCAN = ['app', 'tests', 'database', 'routes', 'config', 'bootstrap']
NS = re.compile(r'^namespace\s+([^;]+);', re.M)
DECL = re.compile(r'^(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+(\w+)', re.M)


def ns_for(rel: str) -> str:
    return '\\'.join(['App', *Path(rel).parent.parts[1:]])


def php_files():
    for d in SCAN:
        yield from sorted((ROOT / d).rglob('*.php'))


def main(moves_file: str) -> None:
    moves = {}
    for line in Path(moves_file).read_text().splitlines():
        if line.strip():
            old, new = line.split()
            assert (ROOT / old).exists(), f'missing: {old}'
            moves[old] = new

    info = {}  # rel path -> (namespace, class) before the move
    for f in php_files():
        rel = str(f.relative_to(ROOT))
        if rel.startswith('app/'):
            text = f.read_text()
            n, c = NS.search(text), DECL.search(text)
            if n and c:
                info[rel] = (n.group(1), c.group(1))

    post_ns = {rel: (ns_for(moves[rel]) if rel in moves else ns) for rel, (ns, _) in info.items()}
    by_ns = {}
    for rel, (ns, cls) in info.items():
        by_ns.setdefault(ns, []).append((cls, rel))

    renames = {f'{info[rel][0]}\\{info[rel][1]}': f'{post_ns[rel]}\\{info[rel][1]}' for rel in moves}

    extra_uses = {}
    for rel, (ns, _cls) in info.items():
        text = (ROOT / rel).read_text()
        body = '\n'.join(l for l in text.splitlines() if not l.startswith(('use ', 'namespace ')))
        for other_cls, other_rel in by_ns.get(ns, []):
            if other_rel == rel or post_ns[other_rel] == post_ns[rel]:
                continue
            imported = re.search(rf'^use [^;]*\\{other_cls};|^use [^;]* as {other_cls};', text, re.M)
            if not imported and re.search(rf'(?<![\w\\]){other_cls}(?!\w)', body):
                extra_uses.setdefault(rel, []).append(f'{post_ns[other_rel]}\\{other_cls}')

    for old, new in moves.items():
        (ROOT / new).parent.mkdir(parents=True, exist_ok=True)
        subprocess.run(['git', 'mv', old, new], cwd=ROOT, check=True)

    new_to_old = {new: old for old, new in moves.items()}
    ordered = sorted(renames.items(), key=lambda kv: -len(kv[0]))
    touched = 0
    for f in php_files():
        rel = str(f.relative_to(ROOT))
        orig = new_to_old.get(rel, rel)
        text = before = f.read_text()
        if orig in moves:
            text = NS.sub(lambda _m: f'namespace {post_ns[orig]};', text, count=1)
        for old, new in ordered:
            text = re.sub(rf'(?<!\w)(\\?){re.escape(old)}(?!\w)', lambda m, n=new: m.group(1) + n, text)
            text = text.replace(old.replace('\\', '\\\\'), new.replace('\\', '\\\\'))
        uses = sorted(set(extra_uses.get(orig, [])))
        if uses:
            block = '\n'.join(f'use {u};' for u in uses)
            text = NS.sub(lambda m: f'{m.group(0)}\n\n{block}', text, count=1)
        if text != before:
            f.write_text(text)
            touched += 1

    subprocess.run(['find', 'app', '-type', 'd', '-empty', '-delete'], cwd=ROOT, check=True)
    print(f'moved {len(moves)} files, {len(renames)} renamed classes, '
          f'{sum(len(v) for v in extra_uses.values())} implicit imports added, {touched} files rewritten')


if __name__ == '__main__':
    main(sys.argv[1])
```

- [ ] **Step 2: Smoke-test the tool's syntax**

Run: `python3 -I -m py_compile $SCRATCH/move_module.py && echo ok`
Expected: `ok`.

---

### Task 2: Architecture test + Shared kernel

**Files:**
- Create: `tests/Architecture/ModuleBoundariesTest.php`
- Modify: `phpunit.xml` (add suite)
- Create: `app/Modules/Shared/SharedServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Move (via tool): `Money`, `AsMoney`, `BelongsToUser`, `Controller`, `HandleInertiaRequests`

- [ ] **Step 1: Write the architecture test**

```php
<?php

namespace Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ModuleBoundariesTest extends TestCase
{
    /** Directories every module may reference in any other module. */
    private const OPEN_SEGMENTS = ['Contracts', 'Events', 'Enums'];

    /** Modules that only read other modules' data and may import their models. */
    private const READ_SIDE_MODULES = ['Reporting', 'Notifications'];

    private const USER_MODEL = 'App\\Modules\\Identity\\Models\\User';

    /**
     * @return array<string, list<string>> offending file => referenced classes
     */
    private function violations(): array
    {
        $root = dirname(__DIR__, 2).'/app/Modules';
        $violations = [];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root) + 1);
            [$module, $area] = array_pad(explode('/', $relative), 2, '');

            preg_match_all(
                '/App\\\\Modules\\\\(\w+)\\\\([\w\\\\]+)/',
                (string) file_get_contents($file->getPathname()),
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as [$full, $target, $path]) {
                if ($target === $module || $target === 'Shared' || $full === self::USER_MODEL) {
                    continue;
                }

                $segment = explode('\\', $path)[0];

                if (in_array($segment, self::OPEN_SEGMENTS, true)) {
                    continue;
                }

                if ($segment === 'Models' && ($area === 'Models' || in_array($module, self::READ_SIDE_MODULES, true))) {
                    continue;
                }

                $violations[$relative][] = $full;
            }
        }

        return array_map(fn (array $classes) => array_values(array_unique($classes)), $violations);
    }

    public function test_modules_only_touch_each_other_through_their_public_surface(): void
    {
        $this->assertSame([], $this->violations(), 'Module boundary violations found.');
    }
}
```

- [ ] **Step 2: Register the suite**

In `phpunit.xml`, after the `Feature` testsuite element add:

```xml
        <testsuite name="Architecture">
            <directory>tests/Architecture</directory>
        </testsuite>
```

- [ ] **Step 3: Run it (passes: nothing under `app/Modules` yet)**

Run: `vendor/bin/sail artisan test --compact tests/Architecture`
Expected: `1 passed`. (A `RecursiveDirectoryIterator` error because `app/Modules` does not exist yet means: run Step 4 first, then re-run.)

- [ ] **Step 4: Move the shared kernel**

Create `$SCRATCH/moves.shared.txt`:

```
app/Support/Money.php app/Modules/Shared/Support/Money.php
app/Casts/AsMoney.php app/Modules/Shared/Casts/AsMoney.php
app/Models/Concerns/BelongsToUser.php app/Modules/Shared/Concerns/BelongsToUser.php
app/Http/Controllers/Controller.php app/Modules/Shared/Http/Controllers/Controller.php
app/Http/Middleware/HandleInertiaRequests.php app/Modules/Shared/Http/Middleware/HandleInertiaRequests.php
```

Run: `python3 -I $SCRATCH/move_module.py $SCRATCH/moves.shared.txt`
Expected: `moved 5 files, 5 renamed classes, ... implicit imports added ...`.

- [ ] **Step 5: Add the Shared provider (factory/model name resolution across modules)**

`app/Modules/Shared/SharedServiceProvider.php`:

```php
<?php

namespace App\Modules\Shared;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class SharedServiceProvider extends ServiceProvider
{
    /**
     * Factories stay in database/factories while models live in modules, so
     * Laravel's namespace-based guessing is replaced with an explicit lookup.
     */
    public function boot(): void
    {
        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        Factory::guessModelNamesUsing(function (Factory $factory): string {
            $model = Str::beforeLast(class_basename($factory), 'Factory');

            foreach (glob(app_path("Modules/*/Models/{$model}.php")) ?: [] as $file) {
                return 'App\\Modules\\'.basename(dirname($file, 2)).'\\Models\\'.$model;
            }

            return 'App\\Models\\'.$model;
        });
    }
}
```

Modify `bootstrap/providers.php`:

```php
<?php

use App\Modules\Shared\SharedServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
];
```

- [ ] **Step 6: Format, clear caches, run everything**

Run: `vendor/bin/sail bin pint --dirty --format agent && vendor/bin/sail artisan optimize:clear && vendor/bin/sail artisan test --compact`
Expected: `129 passed` (128 + architecture test).

- [ ] **Step 7: Commit**

```bash
git add -A app tests database routes config bootstrap phpunit.xml
git commit -m "refactor(shared): move shared kernel into app/Modules/Shared and add boundary test"
```

---

### Task 3: Identity module

**Files:** moves only (plus tests/config references rewritten by the tool).

- [ ] **Step 1: Move**

Create `$SCRATCH/moves.identity.txt`:

```
app/Models/User.php app/Modules/Identity/Models/User.php
app/Http/Controllers/Auth/AuthenticatedSessionController.php app/Modules/Identity/Http/Controllers/Auth/AuthenticatedSessionController.php
app/Http/Controllers/Auth/ConfirmablePasswordController.php app/Modules/Identity/Http/Controllers/Auth/ConfirmablePasswordController.php
app/Http/Controllers/Auth/EmailVerificationNotificationController.php app/Modules/Identity/Http/Controllers/Auth/EmailVerificationNotificationController.php
app/Http/Controllers/Auth/EmailVerificationPromptController.php app/Modules/Identity/Http/Controllers/Auth/EmailVerificationPromptController.php
app/Http/Controllers/Auth/NewPasswordController.php app/Modules/Identity/Http/Controllers/Auth/NewPasswordController.php
app/Http/Controllers/Auth/PasswordController.php app/Modules/Identity/Http/Controllers/Auth/PasswordController.php
app/Http/Controllers/Auth/PasswordResetLinkController.php app/Modules/Identity/Http/Controllers/Auth/PasswordResetLinkController.php
app/Http/Controllers/Auth/RegisteredUserController.php app/Modules/Identity/Http/Controllers/Auth/RegisteredUserController.php
app/Http/Controllers/Auth/VerifyEmailController.php app/Modules/Identity/Http/Controllers/Auth/VerifyEmailController.php
app/Http/Controllers/ProfileController.php app/Modules/Identity/Http/Controllers/ProfileController.php
app/Http/Controllers/PreferenceController.php app/Modules/Identity/Http/Controllers/PreferenceController.php
app/Http/Requests/Auth/LoginRequest.php app/Modules/Identity/Http/Requests/Auth/LoginRequest.php
app/Http/Requests/ProfileUpdateRequest.php app/Modules/Identity/Http/Requests/ProfileUpdateRequest.php
app/Http/Requests/Preferences/UpdatePreferencesRequest.php app/Modules/Identity/Http/Requests/Preferences/UpdatePreferencesRequest.php
```

Run: `python3 -I $SCRATCH/move_module.py $SCRATCH/moves.identity.txt`
Expected: `moved 15 files, 15 renamed classes, ...`.

- [ ] **Step 2: Verify config and the User model's relations**

Run: `grep -n "Modules" config/auth.php && sed -n 1,20p app/Modules/Identity/Models/User.php`
Expected: `config/auth.php` uses `App\Modules\Identity\Models\User`; `User.php` has `use App\Models\Account;` etc. lines (added by the tool, rewritten to new names in later tasks).

- [ ] **Step 3: Format, clear, test**

Run: `vendor/bin/sail bin pint --dirty --format agent && vendor/bin/sail artisan optimize:clear && vendor/bin/sail artisan test --compact`
Expected: `129 passed`.

- [ ] **Step 4: Commit**

```bash
git add -A app tests database routes config bootstrap
git commit -m "refactor(identity): move user, auth, profile and preferences into app/Modules/Identity"
```

---

### Task 4: Ledger module + contracts

**Files:**
- Move (via tool): see list below
- Create: `app/Modules/Ledger/Contracts/{Balances,LedgerQuery,RecordsTransactions}.php`
- Create: `app/Modules/Ledger/Services/EloquentLedgerQuery.php`
- Create: `app/Modules/Ledger/LedgerServiceProvider.php`
- Modify: `app/Modules/Ledger/Services/BalanceCalculator.php` (implements `Balances`), `TransactionService.php` (implements `RecordsTransactions`), `app/Modules/Shared/Http/Middleware/HandleInertiaRequests.php` (use `Balances`), `app/Providers/AppServiceProvider.php`, `bootstrap/providers.php`
- Test: `tests/Feature/ModuleContractsTest.php`

- [ ] **Step 1: Write the failing contract test**

```php
<?php

namespace Tests\Feature;

use App\Modules\Ledger\Contracts\Balances;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Contracts\RecordsTransactions;
use App\Modules\Ledger\Enums\CategoryType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Ledger\Policies\AccountPolicy;
use App\Modules\Ledger\Policies\CategoryPolicy;
use App\Modules\Ledger\Policies\TransactionPolicy;
use App\Modules\Ledger\Services\BalanceCalculator;
use App\Modules\Ledger\Services\EloquentLedgerQuery;
use App\Modules\Ledger\Services\TransactionService;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ModuleContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_contracts_resolve_to_their_implementations(): void
    {
        $this->assertInstanceOf(BalanceCalculator::class, app(Balances::class));
        $this->assertInstanceOf(EloquentLedgerQuery::class, app(LedgerQuery::class));
        $this->assertInstanceOf(TransactionService::class, app(RecordsTransactions::class));
    }

    public function test_ledger_models_resolve_their_policies(): void
    {
        $this->assertInstanceOf(AccountPolicy::class, Gate::getPolicyFor(Account::class));
        $this->assertInstanceOf(CategoryPolicy::class, Gate::getPolicyFor(Category::class));
        $this->assertInstanceOf(TransactionPolicy::class, Gate::getPolicyFor(Transaction::class));
    }

    public function test_expense_total_sums_only_the_matching_category_currency_and_period(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $otherCategory = Category::factory()->expense()->create(['user_id' => $user->id]);
        $cop = Account::factory()->for($user)->create(['currency' => 'COP']);
        $usd = Account::factory()->for($user)->create(['currency' => 'USD']);

        $make = fn (Account $account, Category $cat, string $date, float $amount) => Transaction::factory()
            ->for($user)->for($account)->create(['category_id' => $cat->id, 'date' => $date, 'amount' => $amount]);

        $make($cop, $category, '2026-10-05', 100.50);
        $make($cop, $category, '2026-10-20', 50.00);
        $make($cop, $category, '2026-09-30', 999.00); // outside period
        $make($usd, $category, '2026-10-06', 777.00); // other currency
        $make($cop, $otherCategory, '2026-10-07', 555.00); // other category

        $total = app(LedgerQuery::class)->expenseTotal(
            $category->id,
            'COP',
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
        );

        $this->assertSame('150.50', $total->toDecimalString());
    }

    public function test_account_and_category_options_are_scoped_to_the_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Account::factory()->for($user)->create(['name' => 'Active', 'is_active' => true]);
        Account::factory()->for($user)->inactive()->create(['name' => 'Archived']);
        Account::factory()->create(['name' => 'Someone else']);
        Category::factory()->expense()->create(['user_id' => $user->id, 'name' => 'Mine']);

        $ledger = app(LedgerQuery::class);

        $this->assertSame(['Active'], $ledger->activeAccountOptions()->pluck('name')->all());
        $this->assertContains('Mine', $ledger->categoryOptions(CategoryType::Expense)->pluck('name')->all());
        $this->assertNotContains('Mine', $ledger->categoryOptions(CategoryType::Income)->pluck('name')->all());
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/sail artisan test --compact tests/Feature/ModuleContractsTest.php`
Expected: FAIL (classes under `App\Modules\Ledger` do not exist yet).

- [ ] **Step 3: Move the Ledger files**

Create `$SCRATCH/moves.ledger.txt`:

```
app/Models/Account.php app/Modules/Ledger/Models/Account.php
app/Models/Category.php app/Modules/Ledger/Models/Category.php
app/Models/Transaction.php app/Modules/Ledger/Models/Transaction.php
app/Enums/AccountType.php app/Modules/Ledger/Enums/AccountType.php
app/Enums/CategoryType.php app/Modules/Ledger/Enums/CategoryType.php
app/Enums/PaymentMethod.php app/Modules/Ledger/Enums/PaymentMethod.php
app/Enums/TransactionType.php app/Modules/Ledger/Enums/TransactionType.php
app/Services/TransactionService.php app/Modules/Ledger/Services/TransactionService.php
app/Services/BalanceCalculator.php app/Modules/Ledger/Services/BalanceCalculator.php
app/Services/TransactionImportService.php app/Modules/Ledger/Services/TransactionImportService.php
app/Events/TransactionsMutated.php app/Modules/Ledger/Events/TransactionsMutated.php
app/Listeners/InvalidateBalanceCache.php app/Modules/Ledger/Listeners/InvalidateBalanceCache.php
app/Exceptions/TransactionImportException.php app/Modules/Ledger/Exceptions/TransactionImportException.php
app/Policies/AccountPolicy.php app/Modules/Ledger/Policies/AccountPolicy.php
app/Policies/CategoryPolicy.php app/Modules/Ledger/Policies/CategoryPolicy.php
app/Policies/TransactionPolicy.php app/Modules/Ledger/Policies/TransactionPolicy.php
app/Http/Controllers/AccountController.php app/Modules/Ledger/Http/Controllers/AccountController.php
app/Http/Controllers/CategoryController.php app/Modules/Ledger/Http/Controllers/CategoryController.php
app/Http/Controllers/TransactionController.php app/Modules/Ledger/Http/Controllers/TransactionController.php
app/Http/Controllers/TransactionImportController.php app/Modules/Ledger/Http/Controllers/TransactionImportController.php
app/Http/Controllers/TransactionImportTemplateController.php app/Modules/Ledger/Http/Controllers/TransactionImportTemplateController.php
app/Http/Requests/Accounts/StoreAccountRequest.php app/Modules/Ledger/Http/Requests/Accounts/StoreAccountRequest.php
app/Http/Requests/Accounts/UpdateAccountRequest.php app/Modules/Ledger/Http/Requests/Accounts/UpdateAccountRequest.php
app/Http/Requests/Categories/StoreCategoryRequest.php app/Modules/Ledger/Http/Requests/Categories/StoreCategoryRequest.php
app/Http/Requests/Categories/UpdateCategoryRequest.php app/Modules/Ledger/Http/Requests/Categories/UpdateCategoryRequest.php
app/Http/Requests/Transactions/StoreTransactionRequest.php app/Modules/Ledger/Http/Requests/Transactions/StoreTransactionRequest.php
app/Http/Requests/Transactions/UpdateTransactionRequest.php app/Modules/Ledger/Http/Requests/Transactions/UpdateTransactionRequest.php
```

Run: `python3 -I $SCRATCH/move_module.py $SCRATCH/moves.ledger.txt`
Expected: `moved 26 files ...`.

- [ ] **Step 4: Create the contracts**

`app/Modules/Ledger/Contracts/Balances.php`:

```php
<?php

namespace App\Modules\Ledger\Contracts;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Shared\Support\Money;
use Illuminate\Support\Collection;

/**
 * Derived account balances and net worth (never stored, always computed from the ledger).
 */
interface Balances
{
    public function accountBalance(Account $account): Money;

    public function netWorth(User $user): Money;

    /**
     * @return Collection<string, Money> net worth keyed by currency code
     */
    public function netWorthByCurrency(User $user): Collection;

    public function forgetAccount(Account $account): void;
}
```

`app/Modules/Ledger/Contracts/LedgerQuery.php`:

```php
<?php

namespace App\Modules\Ledger\Contracts;

use App\Modules\Ledger\Enums\CategoryType;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Read-only questions other modules ask the ledger. Everything is scoped to the current user.
 */
interface LedgerQuery
{
    /**
     * Sum of expense transactions in one category, in accounts of one currency, between two dates (inclusive).
     */
    public function expenseTotal(int $categoryId, string $currency, CarbonInterface $from, CarbonInterface $to): Money;

    /**
     * @return Collection<int, object{id: int, name: string}> active accounts ordered by name
     */
    public function activeAccountOptions(): Collection;

    /**
     * @return Collection<int, object{id: int, name: string}> categories of one type ordered by name
     */
    public function categoryOptions(CategoryType $type): Collection;
}
```

`app/Modules/Ledger/Contracts/RecordsTransactions.php`:

```php
<?php

namespace App\Modules\Ledger\Contracts;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Enums\TransactionType;

/**
 * The only way other modules create transactions.
 */
interface RecordsTransactions
{
    /**
     * Record an income or expense generated from a recurring template.
     *
     * @param  array<string, mixed>  $data  account_id, category_id, amount, date, description
     */
    public function recordRecurring(User $user, TransactionType $type, array $data, int $recurringTransactionId): void;
}
```

- [ ] **Step 5: Implement `LedgerQuery` (queries copied from the controllers/BudgetService they replace)**

Before writing, read the originals so the semantics match: `sed -n 36,45p app/Http/Controllers/BudgetForecastController.php` and `sed -n 48,54p app/Http/Controllers/RecurringTransactionController.php` (these still use `Category::where(...)->orderBy('name')->get(['id', 'name'])` and `Account::where('is_active', true)->orderBy('name')->get(['id', 'name'])`). Copy the exact `where`/`orderBy` chains.

`app/Modules/Ledger/Services/EloquentLedgerQuery.php`:

```php
<?php

namespace App\Modules\Ledger\Services;

use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Enums\CategoryType;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EloquentLedgerQuery implements LedgerQuery
{
    public function expenseTotal(int $categoryId, string $currency, CarbonInterface $from, CarbonInterface $to): Money
    {
        return Money::of(
            Transaction::query()
                ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
                ->where('transactions.category_id', $categoryId)
                ->where('transactions.type', TransactionType::Expense->value)
                ->where('accounts.currency', $currency)
                ->whereBetween('transactions.date', [$from, $to])
                ->sum('transactions.amount')
        );
    }

    public function activeAccountOptions(): Collection
    {
        return Account::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function categoryOptions(CategoryType $type): Collection
    {
        return Category::where('type', $type)->orderBy('name')->get(['id', 'name']);
    }
}
```

- [ ] **Step 6: Make the services implement the contracts**

`BalanceCalculator`: change the class line to `class BalanceCalculator implements Balances` and add `use App\Modules\Ledger\Contracts\Balances;`.

`TransactionService`: change to `class TransactionService implements RecordsTransactions`, add `use App\Modules\Ledger\Contracts\RecordsTransactions;` and this method (after `createExpense`):

```php
    public function recordRecurring(User $user, TransactionType $type, array $data, int $recurringTransactionId): void
    {
        $transaction = $type === TransactionType::Income
            ? $this->createIncome($user, $data)
            : $this->createExpense($user, $data);

        $transaction->update([
            'is_recurring_generated' => true,
            'recurring_transaction_id' => $recurringTransactionId,
        ]);
    }
```

`HandleInertiaRequests` (now in Shared): replace the `BalanceCalculator` import and type-hints with `App\Modules\Ledger\Contracts\Balances`.

- [ ] **Step 7: Provider and wiring**

`app/Modules/Ledger/LedgerServiceProvider.php`:

```php
<?php

namespace App\Modules\Ledger;

use App\Modules\Ledger\Contracts\Balances;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Contracts\RecordsTransactions;
use App\Modules\Ledger\Events\TransactionsMutated;
use App\Modules\Ledger\Listeners\InvalidateBalanceCache;
use App\Modules\Ledger\Services\BalanceCalculator;
use App\Modules\Ledger\Services\EloquentLedgerQuery;
use App\Modules\Ledger\Services\TransactionService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class LedgerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Balances::class, BalanceCalculator::class);
        $this->app->bind(LedgerQuery::class, EloquentLedgerQuery::class);
        $this->app->bind(RecordsTransactions::class, TransactionService::class);
    }

    public function boot(): void
    {
        Event::listen(TransactionsMutated::class, InvalidateBalanceCache::class);
    }
}
```

In `app/Providers/AppServiceProvider.php` remove the `InvalidateBalanceCache` listener line and its `use`. Add `LedgerServiceProvider::class` to `bootstrap/providers.php` after `SharedServiceProvider`.

- [ ] **Step 8: Make the test and boundary check pass**

Run: `vendor/bin/sail bin pint --dirty --format agent && vendor/bin/sail artisan optimize:clear && vendor/bin/sail artisan test --compact tests/Feature/ModuleContractsTest.php tests/Architecture`
Expected: PASS. If the architecture test lists violations, they are in Ledger files referencing code that has not moved yet (e.g. `Models\Budget` in `Account::budgets()`); relationships from `Models/` are allowed, so fix any non-model offenders by re-reading the failure output.

- [ ] **Step 9: Full suite**

Run: `vendor/bin/sail artisan test --compact`
Expected: all pass (`133 passed`: 129 + 4 new). Note: `CheckBudgetThreshold` and `CheckLowAccountBalance` are still registered in `AppServiceProvider` pointing at renamed classes. The tool rewrote those references.

- [ ] **Step 10: Commit**

```bash
git add -A app tests database routes config bootstrap
git commit -m "refactor(ledger): move accounts, categories and transactions into app/Modules/Ledger and add ledger contracts"
```

---

### Task 5: Planning module + BudgetProgress contract

**Files:**
- Move (via tool): see list
- Create: `app/Modules/Planning/Contracts/BudgetProgress.php`, `app/Modules/Planning/PlanningServiceProvider.php`
- Modify: `BudgetService.php` (use `LedgerQuery`, implement `BudgetProgress`), `RecurringTransactionService.php` (use `RecordsTransactions`), `BudgetController.php`, `BudgetForecastController.php`, `RecurringTransactionController.php` (use `LedgerQuery` options), `bootstrap/providers.php`
- Test: extend `tests/Feature/ModuleContractsTest.php`

- [ ] **Step 1: Add the failing test**

Append to `ModuleContractsTest` (add imports `App\Modules\Planning\Contracts\BudgetProgress`, `App\Modules\Planning\Services\BudgetService`, `App\Modules\Planning\Models\{Budget,BudgetForecast,SavingsGoal,RecurringTransaction}`, and the four policies from `App\Modules\Planning\Policies`):

```php
    public function test_planning_contract_resolves_to_its_implementation(): void
    {
        $this->assertInstanceOf(BudgetService::class, app(BudgetProgress::class));
    }

    public function test_planning_models_resolve_their_policies(): void
    {
        $this->assertInstanceOf(BudgetPolicy::class, Gate::getPolicyFor(Budget::class));
        $this->assertInstanceOf(BudgetForecastPolicy::class, Gate::getPolicyFor(BudgetForecast::class));
        $this->assertInstanceOf(SavingsGoalPolicy::class, Gate::getPolicyFor(SavingsGoal::class));
        $this->assertInstanceOf(RecurringTransactionPolicy::class, Gate::getPolicyFor(RecurringTransaction::class));
    }
```

Run: `vendor/bin/sail artisan test --compact tests/Feature/ModuleContractsTest.php`
Expected: FAIL (classes missing).

- [ ] **Step 2: Move the Planning files**

Create `$SCRATCH/moves.planning.txt`:

```
app/Models/Budget.php app/Modules/Planning/Models/Budget.php
app/Models/BudgetForecast.php app/Modules/Planning/Models/BudgetForecast.php
app/Models/SavingsGoal.php app/Modules/Planning/Models/SavingsGoal.php
app/Models/SavingsGoalContribution.php app/Modules/Planning/Models/SavingsGoalContribution.php
app/Models/RecurringTransaction.php app/Modules/Planning/Models/RecurringTransaction.php
app/Enums/BudgetPeriodType.php app/Modules/Planning/Enums/BudgetPeriodType.php
app/Enums/RecurringFrequency.php app/Modules/Planning/Enums/RecurringFrequency.php
app/Enums/SavingsGoalStatus.php app/Modules/Planning/Enums/SavingsGoalStatus.php
app/Services/BudgetService.php app/Modules/Planning/Services/BudgetService.php
app/Services/BudgetForecastService.php app/Modules/Planning/Services/BudgetForecastService.php
app/Services/RecurringTransactionService.php app/Modules/Planning/Services/RecurringTransactionService.php
app/Jobs/GenerateDueRecurringTransactions.php app/Modules/Planning/Jobs/GenerateDueRecurringTransactions.php
app/Policies/BudgetPolicy.php app/Modules/Planning/Policies/BudgetPolicy.php
app/Policies/BudgetForecastPolicy.php app/Modules/Planning/Policies/BudgetForecastPolicy.php
app/Policies/RecurringTransactionPolicy.php app/Modules/Planning/Policies/RecurringTransactionPolicy.php
app/Policies/SavingsGoalPolicy.php app/Modules/Planning/Policies/SavingsGoalPolicy.php
app/Http/Controllers/BudgetController.php app/Modules/Planning/Http/Controllers/BudgetController.php
app/Http/Controllers/BudgetForecastController.php app/Modules/Planning/Http/Controllers/BudgetForecastController.php
app/Http/Controllers/SavingsGoalController.php app/Modules/Planning/Http/Controllers/SavingsGoalController.php
app/Http/Controllers/RecurringTransactionController.php app/Modules/Planning/Http/Controllers/RecurringTransactionController.php
app/Http/Requests/Budgets/StoreBudgetRequest.php app/Modules/Planning/Http/Requests/Budgets/StoreBudgetRequest.php
app/Http/Requests/Budgets/UpdateBudgetRequest.php app/Modules/Planning/Http/Requests/Budgets/UpdateBudgetRequest.php
app/Http/Requests/BudgetForecasts/StoreBudgetForecastRequest.php app/Modules/Planning/Http/Requests/BudgetForecasts/StoreBudgetForecastRequest.php
app/Http/Requests/BudgetForecasts/UpdateBudgetForecastRequest.php app/Modules/Planning/Http/Requests/BudgetForecasts/UpdateBudgetForecastRequest.php
app/Http/Requests/RecurringTransactions/StoreRecurringTransactionRequest.php app/Modules/Planning/Http/Requests/RecurringTransactions/StoreRecurringTransactionRequest.php
app/Http/Requests/RecurringTransactions/UpdateRecurringTransactionRequest.php app/Modules/Planning/Http/Requests/RecurringTransactions/UpdateRecurringTransactionRequest.php
app/Http/Requests/SavingsGoals/StoreSavingsGoalContributionRequest.php app/Modules/Planning/Http/Requests/SavingsGoals/StoreSavingsGoalContributionRequest.php
app/Http/Requests/SavingsGoals/StoreSavingsGoalRequest.php app/Modules/Planning/Http/Requests/SavingsGoals/StoreSavingsGoalRequest.php
app/Http/Requests/SavingsGoals/UpdateSavingsGoalRequest.php app/Modules/Planning/Http/Requests/SavingsGoals/UpdateSavingsGoalRequest.php
```

Run: `python3 -I $SCRATCH/move_module.py $SCRATCH/moves.planning.txt`
Expected: `moved 28 files ...`.

- [ ] **Step 3: Create the contract**

`app/Modules/Planning/Contracts/BudgetProgress.php`:

```php
<?php

namespace App\Modules\Planning\Contracts;

use App\Modules\Planning\Models\Budget;
use App\Modules\Shared\Support\Money;

interface BudgetProgress
{
    /**
     * @return array{spent: Money, remaining: Money, percentage: float, status: string}
     */
    public function progress(Budget $budget): array;
}
```

- [ ] **Step 4: `BudgetService` uses `LedgerQuery` instead of querying transactions**

In `BudgetService`: `class BudgetService implements BudgetProgress`, constructor `public function __construct(private readonly LedgerQuery $ledger) {}`, add `use App\Modules\Ledger\Contracts\LedgerQuery;` and `use App\Modules\Planning\Contracts\BudgetProgress;`, delete the `Transaction`/`TransactionType` imports, and replace the `$spent = Money::of(Transaction::query()...->sum(...))` expression with:

```php
        $spent = $this->ledger->expenseTotal($budget->category_id, $budget->currency, $start, $end);
```

- [ ] **Step 5: `RecurringTransactionService` uses `RecordsTransactions`**

Replace the constructor with `public function __construct(private readonly RecordsTransactions $transactions) {}` (import `App\Modules\Ledger\Contracts\RecordsTransactions`, drop the `TransactionService` import) and replace the body of the `DB::transaction` closure's data/transaction/update section in `generateOne` with:

```php
            $data = [
                'account_id' => $recurring->account_id,
                'category_id' => $recurring->category_id,
                'amount' => $recurring->amount,
                'date' => $recurring->next_due_date,
                'description' => $recurring->description,
            ];

            $this->transactions->recordRecurring($recurring->user, $recurring->type, $data, $recurring->id);

            $nextDue = $recurring->frequency->addTo($recurring->next_due_date);
```

(keep the following `$recurring->update([...])` block unchanged).

- [ ] **Step 6: Controllers use `LedgerQuery` for dropdown options**

In `BudgetController`, `BudgetForecastController` and `RecurringTransactionController`: inject `LedgerQuery $ledger` into the action that builds the options and replace the direct model queries:

- `Category::where('type', CategoryType::Expense)->orderBy('name')->get(['id', 'name'])` -> `$ledger->categoryOptions(CategoryType::Expense)`
- `Category::where('type', CategoryType::Income)...` -> `$ledger->categoryOptions(CategoryType::Income)`
- `Account::where('is_active', true)->orderBy('name')->get(['id', 'name'])` -> `$ledger->activeAccountOptions()`
- In `BudgetForecastController` the string `'expense'` comparison becomes `$ledger->categoryOptions(CategoryType::Expense)` (import `App\Modules\Ledger\Enums\CategoryType`).

Remove the now-unused `Account`/`Category` imports from those three controllers.

- [ ] **Step 7: Provider and wiring**

`app/Modules/Planning/PlanningServiceProvider.php`:

```php
<?php

namespace App\Modules\Planning;

use App\Modules\Planning\Contracts\BudgetProgress;
use App\Modules\Planning\Services\BudgetService;
use Illuminate\Support\ServiceProvider;

class PlanningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BudgetProgress::class, BudgetService::class);
    }
}
```

Add `PlanningServiceProvider::class` to `bootstrap/providers.php`.

- [ ] **Step 8: Format, clear, run tests and boundary check**

Run: `vendor/bin/sail bin pint --dirty --format agent && vendor/bin/sail artisan optimize:clear && vendor/bin/sail artisan test --compact`
Expected: all pass (`136 passed`: 133 + 3 new). The architecture test must pass: Planning may not import `Ledger\Services`, `Ledger\Http`, or `Ledger\Policies`. Fix any listed file by routing through the contracts above.

- [ ] **Step 9: Commit**

```bash
git add -A app tests database routes config bootstrap
git commit -m "refactor(planning): move budgets, goals and recurring transactions into app/Modules/Planning"
```

---

### Task 6: Reporting module

**Files:**
- Move (via tool): see list
- Modify: `ReportService.php`, `DashboardController.php` (use `Balances` instead of `BalanceCalculator`)

- [ ] **Step 1: Move**

Create `$SCRATCH/moves.reporting.txt`:

```
app/Services/ReportService.php app/Modules/Reporting/Services/ReportService.php
app/Services/InsightService.php app/Modules/Reporting/Services/InsightService.php
app/Http/Controllers/DashboardController.php app/Modules/Reporting/Http/Controllers/DashboardController.php
app/Http/Controllers/ReportController.php app/Modules/Reporting/Http/Controllers/ReportController.php
app/Http/Controllers/Reports/AnnualReportPdfController.php app/Modules/Reporting/Http/Controllers/Reports/AnnualReportPdfController.php
app/Http/Controllers/Reports/CustomReportCsvController.php app/Modules/Reporting/Http/Controllers/Reports/CustomReportCsvController.php
app/Http/Controllers/Reports/MonthlyReportPdfController.php app/Modules/Reporting/Http/Controllers/Reports/MonthlyReportPdfController.php
```

Run: `python3 -I $SCRATCH/move_module.py $SCRATCH/moves.reporting.txt`
Expected: `moved 7 files ...`.

- [ ] **Step 2: Replace `BalanceCalculator` with the `Balances` contract in Reporting**

In `ReportService` and `DashboardController`: change `use App\Modules\Ledger\Services\BalanceCalculator;` to `use App\Modules\Ledger\Contracts\Balances;` and the constructor/method type-hints from `BalanceCalculator $balances` to `Balances $balances`.

- [ ] **Step 3: Format, clear, test**

Run: `vendor/bin/sail bin pint --dirty --format agent && vendor/bin/sail artisan optimize:clear && vendor/bin/sail artisan test --compact`
Expected: `136 passed`, including `ModuleBoundariesTest`. (Reporting is read-side, so importing `Ledger\Models\Transaction` and friends is allowed; importing `Ledger\Services\*` is not.)

- [ ] **Step 4: Commit**

```bash
git add -A app tests database routes config bootstrap
git commit -m "refactor(reporting): move reports, insights and dashboard into app/Modules/Reporting"
```

---

### Task 7: Notifications module

**Files:**
- Move (via tool): see list
- Create: `app/Modules/Notifications/NotificationsServiceProvider.php`
- Create: `database/migrations/2026_10_12_000000_rename_notification_types_for_modules.php`
- Modify: `CheckBudgetThreshold.php` (use `BudgetProgress`), `CheckLowAccountBalance.php` (use `Balances`), `app/Providers/AppServiceProvider.php`, `bootstrap/providers.php`
- Test: `tests/Feature/NotificationsTest.php` (extend)

- [ ] **Step 1: Move**

Create `$SCRATCH/moves.notifications.txt`:

```
app/Notifications/BudgetThresholdReached.php app/Modules/Notifications/Notifications/BudgetThresholdReached.php
app/Notifications/GoalBehindSchedule.php app/Modules/Notifications/Notifications/GoalBehindSchedule.php
app/Notifications/LowAccountBalance.php app/Modules/Notifications/Notifications/LowAccountBalance.php
app/Notifications/UpcomingRecurringPayment.php app/Modules/Notifications/Notifications/UpcomingRecurringPayment.php
app/Listeners/CheckBudgetThreshold.php app/Modules/Notifications/Listeners/CheckBudgetThreshold.php
app/Listeners/CheckLowAccountBalance.php app/Modules/Notifications/Listeners/CheckLowAccountBalance.php
app/Console/Commands/NotifyBehindSavingsGoals.php app/Modules/Notifications/Console/Commands/NotifyBehindSavingsGoals.php
app/Console/Commands/NotifyUpcomingRecurringPayments.php app/Modules/Notifications/Console/Commands/NotifyUpcomingRecurringPayments.php
app/Http/Controllers/NotificationController.php app/Modules/Notifications/Http/Controllers/NotificationController.php
```

Run: `python3 -I $SCRATCH/move_module.py $SCRATCH/moves.notifications.txt`
Expected: `moved 9 files ...`.

- [ ] **Step 2: Use contracts instead of foreign services**

`CheckBudgetThreshold`: replace the `BudgetService` import/type-hint with `App\Modules\Planning\Contracts\BudgetProgress` (constructor property stays `$this->budgets`, calling `->progress($budget)`).
`CheckLowAccountBalance`: replace `BalanceCalculator` with `App\Modules\Ledger\Contracts\Balances`.

- [ ] **Step 3: Provider (explicit listener and command registration)**

`app/Modules/Notifications/NotificationsServiceProvider.php`:

```php
<?php

namespace App\Modules\Notifications;

use App\Modules\Ledger\Events\TransactionsMutated;
use App\Modules\Notifications\Console\Commands\NotifyBehindSavingsGoals;
use App\Modules\Notifications\Console\Commands\NotifyUpcomingRecurringPayments;
use App\Modules\Notifications\Listeners\CheckBudgetThreshold;
use App\Modules\Notifications\Listeners\CheckLowAccountBalance;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(TransactionsMutated::class, CheckBudgetThreshold::class);
        Event::listen(TransactionsMutated::class, CheckLowAccountBalance::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                NotifyBehindSavingsGoals::class,
                NotifyUpcomingRecurringPayments::class,
            ]);
        }
    }
}
```

In `app/Providers/AppServiceProvider.php` remove the remaining `Event::listen` lines and the listener/event imports (it keeps only `Vite::prefetch`). Add `NotificationsServiceProvider::class` to `bootstrap/providers.php`.

- [ ] **Step 4: Data migration for stored notification types**

`database/migrations/2026_10_12_000000_rename_notification_types_for_modules.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TYPES = [
        'App\\Notifications\\BudgetThresholdReached' => 'App\\Modules\\Notifications\\Notifications\\BudgetThresholdReached',
        'App\\Notifications\\GoalBehindSchedule' => 'App\\Modules\\Notifications\\Notifications\\GoalBehindSchedule',
        'App\\Notifications\\LowAccountBalance' => 'App\\Modules\\Notifications\\Notifications\\LowAccountBalance',
        'App\\Notifications\\UpcomingRecurringPayment' => 'App\\Modules\\Notifications\\Notifications\\UpcomingRecurringPayment',
    ];

    public function up(): void
    {
        foreach (self::TYPES as $old => $new) {
            DB::table('notifications')->where('type', $old)->update(['type' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::TYPES as $old => $new) {
            DB::table('notifications')->where('type', $new)->update(['type' => $old]);
        }
    }
};
```

- [ ] **Step 5: Test that stored types are migrated and unread alerts still de-duplicate**

Append to `tests/Feature/NotificationsTest.php` (adjust the `use` list to include `Illuminate\Support\Facades\DB`, `Illuminate\Support\Str`):

```php
    public function test_legacy_notification_types_are_renamed_by_the_module_migration(): void
    {
        $user = \App\Modules\Identity\Models\User::factory()->create();

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\LowAccountBalance',
            'notifiable_type' => $user::class,
            'notifiable_id' => $user->id,
            'data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_12_000000_rename_notification_types_for_modules.php');
        $migration->up();

        $this->assertDatabaseHas('notifications', [
            'type' => \App\Modules\Notifications\Notifications\LowAccountBalance::class,
        ]);
        $this->assertDatabaseMissing('notifications', ['type' => 'App\\Notifications\\LowAccountBalance']);
    }
```

- [ ] **Step 6: Run migrations, verify registrations did not change, test**

Run:
```bash
vendor/bin/sail bin pint --dirty --format agent && vendor/bin/sail artisan optimize:clear
vendor/bin/sail artisan migrate --no-interaction
vendor/bin/sail artisan event:list > $SCRATCH/events.after.txt
vendor/bin/sail artisan list --raw | awk '{print $1}' | sort > $SCRATCH/commands.after.txt
diff $SCRATCH/commands.before.txt $SCRATCH/commands.after.txt && echo "commands unchanged"
diff $SCRATCH/events.before.txt $SCRATCH/events.after.txt
vendor/bin/sail artisan test --compact
```
Expected: `commands unchanged`. The `events` diff may only show renamed class names, and possibly that each listener now appears **once** where it previously appeared twice (explicit registration plus auto-discovery); that is a behaviour fix, so note it in the PR. Suite: `137 passed`.

- [ ] **Step 7: Commit**

```bash
git add -A app tests database routes config bootstrap
git commit -m "refactor(notifications): move alerts, listeners and commands into app/Modules/Notifications"
```

---

### Task 8: Lock the layout, update docs and rules

**Files:**
- Modify: `tests/Architecture/ModuleBoundariesTest.php` (legacy-folder check), `app/Modules/Shared/SharedServiceProvider.php` (drop fallback)
- Modify: `docs/architecture.md`, `docs/domain-model.md`, `docs/testing-conventions.md`, `CLAUDE.md` (project section), `docs/user-manual.md` (no user-visible change; add nothing)
- Create (if `record-rule` is unavailable): `.ai/rules/modules.md`, update `.ai/rules/index.md`

- [ ] **Step 1: Failing test for leftover type folders**

Add to `ModuleBoundariesTest`:

```php
    public function test_type_folders_have_been_replaced_by_modules(): void
    {
        $app = dirname(__DIR__, 2).'/app';

        foreach (['Models', 'Services', 'Enums', 'Events', 'Listeners', 'Policies', 'Jobs', 'Notifications', 'Support', 'Casts', 'Exceptions', 'Console', 'Http'] as $legacy) {
            $this->assertDirectoryDoesNotExist("{$app}/{$legacy}", "app/{$legacy} should live inside a module");
        }
    }
```

Run: `vendor/bin/sail artisan test --compact tests/Architecture`
Expected: PASS if every move emptied its folders. If it fails, `ls app/<dir>` shows what was missed: move it into the right module with the tool.

- [ ] **Step 2: Make factory model lookup strict**

In `SharedServiceProvider::boot`, replace `return 'App\\Models\\'.$model;` with:

```php
            throw new \RuntimeException('No module model found for factory '.$factory::class);
```

Run: `vendor/bin/sail artisan test --compact`
Expected: all pass (`138 passed`).

- [ ] **Step 3: Update the docs**

- `docs/architecture.md`: add a "Modules" section containing the module table, the five boundary rules from the spec, the contract list, how to add a new module (folder, provider in `bootstrap/providers.php`, nothing else), and a note that the architecture test enforces it. Replace statements that describe `app/Services`, `app/Models` etc. with module paths.
- `docs/domain-model.md`: change the "Key services (`app/Services/`)" heading and entries to their module paths; list table ownership per module; mention the contracts.
- `docs/testing-conventions.md`: add a short "Architecture test" section (`tests/Architecture`, strict, how to read a failure).
- `CLAUDE.md` project section (above the `<laravel-boost-guidelines>` block): add "Code lives in `app/Modules/{Shared,Identity,Ledger,Planning,Reporting,Notifications}`; modules talk only through `Contracts`, `Events`, `Enums`; `tests/Architecture` enforces it."

- [ ] **Step 4: Record the rules**

Try the Boost tool first: call `record-rule` with `glob: app/Modules/**`, title "Module boundaries", note "Modules may only reference another module's Contracts/Events/Enums (plus Shared and Identity User). Foreign Models are allowed only inside Models/ or in the read-side modules Reporting and Notifications. Cross-module writes go through contracts. tests/Architecture enforces this."
If the Boost MCP server is unavailable, create `.ai/rules/modules.md` with that note (same frontmatter format as `general.md`, `paths: ['app/Modules/**']`) and add the row `| app/Modules/** | .ai/rules/modules.md |` to `.ai/rules/index.md`. Tell the user which route was used.

- [ ] **Step 5: Final verification**

Run:
```bash
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail artisan optimize:clear
vendor/bin/sail artisan route:list --except-vendor | wc -l   # compare with `git stash`-free baseline noted in Task 0 if needed
vendor/bin/sail artisan test --compact
vendor/bin/sail npm run build
```
Expected: suite green; the build succeeds (Wayfinder regenerates from the moved controllers). Then open http://localhost:8080 as `test@example.com` / `password` and check Dashboard, Transactions, Budgets, Reports load with no errors.

- [ ] **Step 6: Commit docs and lock-in separately**

```bash
git add tests/Architecture app/Modules/Shared/SharedServiceProvider.php
git commit -m "test(architecture): forbid legacy type folders and unresolved factory models"
git add docs CLAUDE.md .ai
git commit -m "docs(architecture): document modules, contracts and boundary rules"
```

- [ ] **Step 7: Push and open the PR (confirm with the user first)**

Ask the user to confirm, then:

```bash
git push -u origin feat/project-budgeting
gh pr create --base main --title "refactor: modular monolith foundation" --body "$(cat <<'EOF'
## Summary
- Move `app/` into six modules (Shared, Identity, Ledger, Planning, Reporting, Notifications) with enforced boundaries
- Add contracts: Balances, LedgerQuery, RecordsTransactions, BudgetProgress
- Add architecture test, boundary rules, updated docs and end-user manual
- Roadmap and decisions: docs/superpowers/specs and plans

## Notes for deploy
- A data migration renames stored notification types; flush queued jobs that reference old class names.
- Listeners are now registered once (previously explicit registration plus auto-discovery).

## Test plan
- [ ] `vendor/bin/sail artisan test --compact`
- [ ] Click through Dashboard, Transactions, Budgets, Recurring, Reports

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Self-review (against the spec)
- **Modules and ownership:** Tasks 2–7 move every file listed in the spec's table (Shared, Identity, Ledger, Planning, Reporting, Notifications).
- **Boundary rules:** encoded in `ModuleBoundariesTest` (Task 2) and made to pass by contracts in Tasks 4, 5, 6, 7.
- **Contracts:** `Balances`, `LedgerQuery`, `RecordsTransactions` (Task 4), `BudgetProgress` (Task 5) with names consistent in later tasks.
- **Traps:** policy discovery (Tasks 4, 5 tests), factories (Task 2 provider), notification types (Task 7 migration + test), config references (tool rewrites `config/`, `routes/`, `bootstrap/`), event/command auto-discovery (Task 7 provider + before/after diff), queue payloads (PR deploy note).
- **No behaviour change:** each task runs the full suite; Task 8 adds a manual click-through and a frontend build.
