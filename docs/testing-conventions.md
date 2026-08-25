# Testing Conventions

PHPUnit only — **no Pest**, per project convention. Every test class extends `Tests\TestCase` and uses `Illuminate\Foundation\Testing\RefreshDatabase`. Method names are `snake_case` starting with `test_`, not `#[Test]`-annotated camelCase.

```bash
vendor/bin/sail artisan test --compact                              # full suite
vendor/bin/sail artisan test --compact tests/Feature/BudgetsTest.php # one file
vendor/bin/sail artisan test --compact --filter=test_user_can_create_a_budget
```

## Asserting Inertia responses

```php
use Inertia\Testing\AssertableInertia as Assert;

$response = $this->actingAs($user)->get('/planning/budgets');

$response->assertOk();
$response->assertInertia(fn (Assert $page) => $page
    ->component('Budgets/Index')
    ->has('budgets', 2)          // exact count
    ->where('typeFilter', 'all') // exact value
    ->has('currencies')          // just presence
);
```

Import from **`Inertia\Testing\AssertableInertia`**, not `Illuminate\Testing\Fluent\AssertableInertia` — the latter doesn't exist for this package version and produces a type error at the `assertInertia()` call site, not an import-time error, so it's easy to misdiagnose.

`component()` verifies the named `.vue` file actually exists on disk (`config('inertia.pages.testing.ensure_pages_exist')`, default on) — this is what `config/inertia.php`'s `pages.paths` fix (see `architecture.md`) makes possible at all.

## Ownership tests: expect 404, not 403

Because nearly every model uses `BelongsToUser` (see `domain-model.md`), route-model binding itself filters out another user's row — a policy's `update`/`delete` check never even runs for a genuinely cross-tenant request, since the model was never found:

```php
public function test_user_cannot_update_another_users_budget(): void
{
    $owner = User::factory()->create();
    $budget = Budget::factory()->for($owner)->create();
    $intruder = User::factory()->create();

    $response = $this->actingAs($intruder)->patch("/planning/budgets/{$budget->id}", [...]);

    $response->assertNotFound(); // not assertForbidden()
}
```

`Category` is the exception — its custom `visibleToUser` scope means a **system** category (`user_id === null`) IS visible to everyone, so an intruder editing a system category correctly gets a 404→policy-reachable→**403** (`isEditable()` returns false), while editing another user's *private* category still 404s (never visible at all). Don't assume 404-vs-403 without checking which scope the specific model uses.

## Soft deletes

`Account`, `Category`, `SavingsGoal`, and `Transaction` use `SoftDeletes`; `Budget` and `RecurringTransaction` don't. Use the right assertion:

```php
$this->assertSoftDeleted('accounts', ['id' => $account->id]);   // soft-delete models
$this->assertDatabaseMissing('budgets', ['id' => $budget->id]); // hard-delete models
```

`assertDatabaseMissing` on a soft-deleted row fails (the row is still physically present with `deleted_at` set) — this is the most common false failure when writing a new delete test; check the model's traits before assuming which assertion applies.

## Factories and cross-model foreign keys

Every domain model has a factory in `database/factories/`. The one sharp edge: **`Account` (like most models) is itself `BelongsToUser`-scoped**, so a `RecurringTransaction`/`Transaction`/`Budget` factory's default `account_id => Account::factory()` creates an account owned by a *fresh, unrelated* user unless you explicitly tie it to the same user:

```php
// Wrong — $account belongs to some other random factory-created user.
$recurring = RecurringTransaction::factory()->for($user)->create();

// Right — account_id and user_id agree.
$account = Account::factory()->for($user)->create();
$category = Category::factory()->expense()->create(['user_id' => $user->id]);
$recurring = RecurringTransaction::factory()->for($user)->for($account)->for($category)->create();
```

Getting this wrong doesn't fail at factory-creation time — it fails later, non-obviously, when a controller eager-loads the relation (`->with('account')`) and the `BelongsToUser` scope silently returns `null` for a row that "exists" but isn't visible to the acting user, producing a 500 ("Attempt to read property on null") instead of a validation error. This exact bug was caught writing `RecurringTransactionsTest` and is also why `account_id`/`category_id` Form Request validation is scoped to the current user (see `domain-model.md`) — the same class of bug is reachable from real user input, not just sloppy test setup.

`CategoryFactory::create()` defaults to `user_id: null` (a system category, visible to everyone) — pass `['user_id' => $user->id]` explicitly whenever a test needs a category that's *not* visible to some other user, or the "another user's private category" half of a cross-tenant test will silently pass for the wrong reason (the category is a system one, not actually owned by anyone).

## Deferred props

`netWorth`/`notifications` are shared via `Inertia::defer(..., 'topbar')` (see `architecture.md`) — they are **not** present in a normal page response's `props`, only listed under `deferredProps.topbar` as a promise the client fetches afterward. To assert their actual resolved values, simulate Inertia's partial-reload protocol directly:

```php
$probe = $this->actingAs($user)->getJson('/dashboard', ['X-Inertia' => 'true']);
$version = $probe->headers->get('X-Inertia-Version'); // not knowable ahead of a real request

$partial = $this->actingAs($user)->getJson('/dashboard', [
    'X-Inertia' => 'true',
    'X-Inertia-Version' => $version,
    'X-Inertia-Partial-Data' => 'netWorth,notifications',
    'X-Inertia-Partial-Component' => 'Dashboard',
]);

$partial->assertJsonStructure(['props' => ['netWorth', 'notifications' => ['items', 'unreadCount']]]);
```

Don't try to precompute `X-Inertia-Version` — it's not stable when resolved outside of actual route dispatch (e.g. calling `Inertia::getVersion()` directly from a test body, before making a request, returns a different value than what the middleware computes for the live request). Bootstrap it from a real response's header instead, as above. Sending a *wrong* version on a GET request with the `X-Inertia` header produces a 409, not a 200 with a fallback — this is the most common way this specific test pattern goes subtly wrong.

## What's covered, what isn't

Every domain module from the Inertia migration has feature test coverage (`tests/Feature/{Accounts,Categories,Budgets,SavingsGoals,RecurringTransactions,Transactions,TransactionImport,Reports,Preferences,Dashboard,Notifications,TransactionImportTemplate}Test.php`) — index rendering, create/update/delete happy paths, validation failures, and cross-tenant authorization. Auth flows (`tests/Feature/Auth/*`) and profile management (`ProfileTest.php`) are Breeze's own generated PHPUnit tests, adapted for the Inertia+Vue scaffold rather than rewritten from scratch.

Not covered: the CSV/PDF *content* of report downloads beyond status code + `Content-Type` (real Dompdf/CSV rendering is exercised, but byte-for-byte output isn't asserted), and Vue-side interaction tests (no component-level JS test runner is set up — `GlobalTransactionModal`'s open/edit/submit flow is verified only through the backend endpoints it calls).
