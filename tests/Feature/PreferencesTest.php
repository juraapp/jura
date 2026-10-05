<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_preferences_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings/preferences');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Preferences/Edit')
            ->has('currencies')
            ->has('timezones')
        );
    }

    public function test_user_can_update_their_preferences(): void
    {
        $user = User::factory()->create([
            'currency_default' => 'COP',
            'locale' => 'en',
            'timezone' => 'America/Bogota',
        ]);

        $response = $this->actingAs($user)->patch('/settings/preferences', [
            'currency_default' => 'USD',
            'locale' => 'es',
            'timezone' => 'UTC',
        ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertSame('USD', $user->currency_default);
        $this->assertSame('es', $user->locale);
        $this->assertSame('UTC', $user->timezone);
    }

    public function test_updating_preferences_requires_valid_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/settings/preferences', [
            'currency_default' => 'CO',
            'locale' => 'fr',
            'timezone' => 'Not/A_Timezone',
        ]);

        $response->assertSessionHasErrors(['currency_default', 'locale', 'timezone']);
    }

    public function test_guests_cannot_access_preferences(): void
    {
        $response = $this->get('/settings/preferences');

        $response->assertRedirect('/login');
    }
}
