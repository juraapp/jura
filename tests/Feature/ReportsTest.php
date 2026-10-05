<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_report_page_is_displayed(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        Transaction::factory()->for($user)->for($account)->for($category)->create([
            'date' => now()->startOfMonth()->addDays(2),
            'amount' => 50000,
        ]);

        $response = $this->actingAs($user)->get('/reports/monthly');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Monthly')
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->has('currencies')
        );
    }

    public function test_monthly_report_accepts_year_and_month_query_params(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/reports/monthly?year=2025&month=3');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('year', 2025)
            ->where('month', 3)
        );
    }

    public function test_monthly_report_pdf_can_be_downloaded(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/reports/monthly/pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_annual_report_page_is_displayed(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->income()->create(['user_id' => $user->id]);
        Transaction::factory()->for($user)->for($account)->for($category)->income()->create([
            'date' => now()->startOfYear()->addMonths(2),
            'amount' => 1000000,
        ]);

        $response = $this->actingAs($user)->get('/reports/annual');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Annual')
            ->where('year', now()->year)
            ->has('currencies')
        );
    }

    public function test_annual_report_accepts_a_year_query_param(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/reports/annual?year=2024');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('year', 2024));
    }

    public function test_annual_report_pdf_can_be_downloaded(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/reports/annual/pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_custom_report_page_defaults_to_the_current_month(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/reports/custom');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Custom')
            ->where('from', now()->startOfMonth()->toDateString())
            ->where('to', now()->endOfMonth()->toDateString())
        );
    }

    public function test_custom_report_accepts_from_and_to_query_params(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        Transaction::factory()->for($user)->for($account)->for($category)->create(['date' => '2026-01-15', 'amount' => 20000]);
        Transaction::factory()->for($user)->for($account)->for($category)->create(['date' => '2026-06-15', 'amount' => 30000]); // out of range

        $response = $this->actingAs($user)->get('/reports/custom?from=2026-01-01&to=2026-01-31');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('from', '2026-01-01')
            ->where('to', '2026-01-31')
            ->has('transactions', 1)
        );
    }

    public function test_custom_report_csv_can_be_downloaded(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        Transaction::factory()->for($user)->for($account)->for($category)->create(['date' => now()]);

        $response = $this->actingAs($user)->get('/reports/custom/csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_reports_only_include_the_current_users_transactions(): void
    {
        $user = User::factory()->create();
        $otherAccount = Account::factory()->create();
        $otherCategory = Category::factory()->expense()->create();
        Transaction::factory()->for($otherAccount->user)->for($otherAccount)->for($otherCategory)->create([
            'date' => now(),
            'amount' => 999999,
        ]);

        $response = $this->actingAs($user)->get('/reports/custom');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->has('transactions', 0));
    }

    public function test_guests_cannot_access_reports(): void
    {
        $this->get('/reports/monthly')->assertRedirect('/login');
        $this->get('/reports/annual')->assertRedirect('/login');
        $this->get('/reports/custom')->assertRedirect('/login');
    }
}
