<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionImportTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_download_the_csv_template(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/finances/transactions/import/template');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_guests_cannot_download_the_csv_template(): void
    {
        $response = $this->get('/finances/transactions/import/template');

        $response->assertRedirect('/login');
    }
}
