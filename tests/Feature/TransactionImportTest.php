<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_an_optional_time_column_is_read_in_24_and_12_hour_forms(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['name' => 'My Wallet']);
        $category = Category::factory()->expense()->create(['user_id' => $user->id, 'name' => 'Groceries']);

        $csv = "Date,Time,Description,Category,Account,Type,Notes,Amount\n"
            ."2026-08-01,14:30,Late shop,{$category->name},{$account->name},Expense,,50000\n"
            ."2026-08-01,2:05 PM,Afternoon,{$category->name},{$account->name},Expense,,10000\n"
            ."2026-08-01,,No time,{$category->name},{$account->name},Expense,,5000\n"
            ."2026-08-01,99:99,Bad time,{$category->name},{$account->name},Expense,,1000\n";

        $rows = $this->actingAs($user)->post('/finances/transactions/import/parse', ['file' => $this->csvFile($csv)])->assertOk()->json('rows');

        $this->assertSame(['valid', 'valid', 'valid', 'error'], array_column($rows, 'status'));
        $this->assertStringContainsString('99:99', $rows[3]['errors'][0]);

        $this->actingAs($user)->postJson('/finances/transactions/import', ['rows' => array_column(array_slice($rows, 0, 3), 'raw'), 'include_duplicates' => true])->assertOk();

        $this->assertSame('14:30', Transaction::where('description', 'Late shop')->firstOrFail()->time);
        $this->assertSame('14:05', Transaction::where('description', 'Afternoon')->firstOrFail()->time);
        $this->assertNull(Transaction::where('description', 'No time')->firstOrFail()->time);
    }

    /**
     * Imports one row through the confirm endpoint and returns the stored amount, or null when the row was rejected.
     */
    private function importAmount(User $user, string $amount, string $date = '2026-08-01'): ?string
    {
        $account = Account::factory()->for($user)->create(['name' => 'Wallet']);
        $category = Category::factory()->expense()->create(['user_id' => $user->id, 'name' => 'Groceries']);

        $this->actingAs($user)->postJson('/finances/transactions/import', ['rows' => [[
            'line' => 2, 'date' => $date, 'description' => 'Row', 'category' => $category->name,
            'account' => $account->name, 'type' => 'Expense', 'notes' => null, 'amount' => $amount,
        ]]])->assertOk();

        return Transaction::first()?->amount->toDecimalString();
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function amountsForADotDecimalUser(): array
    {
        return [
            'thousands without decimals' => ['1,234', '1234.00'],
            'thousands with decimals' => ['1,234.56', '1234.56'],
            'spanish style is unambiguous' => ['1.234,56', '1234.56'],
            'comma decimal that cannot be thousands' => ['12,5', '12.50'],
            'exponent' => ['1e3', null],
            'more than two decimals' => ['10.999', null],
            'zero' => ['0', null],
            'beyond decimal(15,2)' => ['100000000000000', null],
        ];
    }

    #[DataProvider('amountsForADotDecimalUser')]
    public function test_amounts_follow_a_dot_decimal_separator_preference(string $raw, ?string $expected): void
    {
        $user = User::factory()->create(['locale' => 'en', 'decimal_separator' => '.']);

        $this->assertSame($expected, $this->importAmount($user, $raw));
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function amountsForACommaDecimalUser(): array
    {
        return [
            'dot thousands' => ['1.234', '1234.00'],
            'comma decimal' => ['1,5', '1.50'],
            'three decimals are not cents' => ['1,234', null],
        ];
    }

    #[DataProvider('amountsForACommaDecimalUser')]
    public function test_amounts_follow_a_comma_decimal_separator_preference(string $raw, ?string $expected): void
    {
        $user = User::factory()->create(['locale' => 'es', 'decimal_separator' => ',']);

        $this->assertSame($expected, $this->importAmount($user, $raw));
    }

    public function test_impossible_dates_are_rejected_instead_of_rolling_over(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->importAmount($user, '100', '31/02/2026'));
        $this->assertNull($this->importAmount($user, '100', '2026-02-31'));
        $this->assertSame('100.00', $this->importAmount($user, '100', '28/02/2026'));
    }

    public function test_confirming_malformed_rows_is_a_validation_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/finances/transactions/import', ['rows' => [['line' => 2]]])->assertOk()->assertJson(['imported' => 0, 'skipped' => 1]);
        $this->actingAs($user)->postJson('/finances/transactions/import', ['rows' => ['not a row']])->assertUnprocessable();
        $this->actingAs($user)->postJson('/finances/transactions/import', ['rows' => [['line' => 2, 'amount' => ['nested']]]])->assertUnprocessable();
        $this->actingAs($user)->postJson('/finances/transactions/import', ['rows' => array_fill(0, 1001, ['line' => 2])])->assertUnprocessable();
    }
}
