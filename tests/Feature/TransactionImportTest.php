<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TransactionImportTest extends TestCase
{
    use RefreshDatabase;

    private function csvFile(string $content): File
    {
        return UploadedFile::fake()->createWithContent('import.csv', $content);
    }

    public function test_import_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/finances/transactions/import');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Transactions/Import'));
    }

    public function test_parsing_a_valid_csv_returns_rows_with_statuses(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['name' => 'My Wallet']);
        $category = Category::factory()->expense()->create(['user_id' => $user->id, 'name' => 'Groceries']);

        $csv = "Date,Description,Category,Account,Type,Notes,Amount\n"
            ."2026-08-01,Weekly shop,{$category->name},{$account->name},Expense,,50000\n"
            .'2026-08-02,Bad row,Nonexistent category,'.$account->name.",Expense,,25000\n";

        $response = $this->actingAs($user)->post('/finances/transactions/import/parse', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertOk();
        $rows = $response->json('rows');
        $this->assertCount(2, $rows);
        $this->assertSame('valid', $rows[0]['status']);
        $this->assertSame('error', $rows[1]['status']);
    }

    public function test_parsing_a_csv_missing_required_columns_returns_an_error(): void
    {
        $user = User::factory()->create();

        $csv = "Foo,Bar\n1,2\n";

        $response = $this->actingAs($user)->post('/finances/transactions/import/parse', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    public function test_parsing_requires_a_file(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/finances/transactions/import/parse', []);

        $response->assertSessionHasErrors(['file']);
    }

    public function test_confirming_import_persists_valid_rows_and_skips_errors(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['name' => 'My Wallet']);
        $category = Category::factory()->expense()->create(['user_id' => $user->id, 'name' => 'Groceries']);

        $response = $this->actingAs($user)->postJson('/finances/transactions/import', [
            'rows' => [
                [
                    'line' => 2,
                    'date' => '2026-08-01',
                    'description' => 'Weekly shop',
                    'category' => $category->name,
                    'account' => $account->name,
                    'type' => 'Expense',
                    'notes' => null,
                    'amount' => '50000',
                ],
                [
                    'line' => 3,
                    'date' => '2026-08-02',
                    'description' => 'Bad row',
                    'category' => 'Nonexistent category',
                    'account' => $account->name,
                    'type' => 'Expense',
                    'notes' => null,
                    'amount' => '25000',
                ],
            ],
            'include_duplicates' => false,
        ]);

        $response->assertOk();
        $response->assertJson(['imported' => 1, 'skipped' => 1]);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'description' => 'Weekly shop',
        ]);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_confirming_import_excludes_duplicates_by_default(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['name' => 'My Wallet']);
        $category = Category::factory()->expense()->create(['user_id' => $user->id, 'name' => 'Groceries']);
        Transaction::factory()->for($user)->for($account)->for($category)->create([
            'date' => '2026-08-01',
            'amount' => 50000,
            'description' => 'Weekly shop',
        ]);

        $response = $this->actingAs($user)->postJson('/finances/transactions/import', [
            'rows' => [[
                'line' => 2,
                'date' => '2026-08-01',
                'description' => 'Weekly shop',
                'category' => $category->name,
                'account' => $account->name,
                'type' => 'Expense',
                'notes' => null,
                'amount' => '50000',
            ]],
            'include_duplicates' => false,
        ]);

        $response->assertOk();
        $response->assertJson(['imported' => 0, 'duplicateSkipped' => 1]);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_confirming_import_includes_duplicates_when_requested(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['name' => 'My Wallet']);
        $category = Category::factory()->expense()->create(['user_id' => $user->id, 'name' => 'Groceries']);
        Transaction::factory()->for($user)->for($account)->for($category)->create([
            'date' => '2026-08-01',
            'amount' => 50000,
            'description' => 'Weekly shop',
        ]);

        $response = $this->actingAs($user)->postJson('/finances/transactions/import', [
            'rows' => [[
                'line' => 2,
                'date' => '2026-08-01',
                'description' => 'Weekly shop',
                'category' => $category->name,
                'account' => $account->name,
                'type' => 'Expense',
                'notes' => null,
                'amount' => '50000',
            ]],
            'include_duplicates' => true,
        ]);

        $response->assertOk();
        $response->assertJson(['imported' => 1, 'duplicateSkipped' => 0]);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_guests_cannot_access_import(): void
    {
        $response = $this->get('/finances/transactions/import');

        $response->assertRedirect('/login');
    }
}
