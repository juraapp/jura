<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
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
            'date_format' => 'iso',
            'time_format' => '24h',
            'decimal_separator' => ',',
            'thousands_separator' => '.',
            'fraction_digits' => 1,
            'currency_display' => 'code',
            'currency_position' => 'before_space',
        ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertSame('USD', $user->currency_default);
        $this->assertSame('es', $user->locale);
        $this->assertSame('UTC', $user->timezone);
        $this->assertSame('iso', $user->date_format);
        $this->assertSame('24h', $user->time_format);
        $this->assertSame(',', $user->decimal_separator);
        $this->assertSame('.', $user->thousands_separator);
        $this->assertSame(1, $user->fraction_digits);
        $this->assertSame('code', $user->currency_display);
        $this->assertSame('before_space', $user->currency_position);
    }

    public function test_format_preferences_default_to_auto_and_are_shared_with_the_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/settings/preferences')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.date_format', 'auto')
            ->where('auth.user.time_format', 'auto')
            ->where('auth.user.currency_display', 'auto')
            ->where('auth.user.decimal_separator', null)
        );
    }

    public function test_format_options_must_be_known_and_the_separators_must_differ(): void
    {
        $user = User::factory()->create();
        $base = ['currency_default' => 'COP', 'locale' => 'en', 'timezone' => 'UTC', 'date_format' => 'auto', 'time_format' => 'auto', 'currency_display' => 'auto', 'currency_position' => 'auto'];

        $this->actingAs($user)->patch('/settings/preferences', array_merge($base, ['date_format' => 'dd-mm', 'fraction_digits' => 5]))
            ->assertSessionHasErrors(['date_format', 'fraction_digits']);
        $this->actingAs($user)->patch('/settings/preferences', array_merge($base, ['decimal_separator' => ',', 'thousands_separator' => ',']))
            ->assertSessionHasErrors('decimal_separator');
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

    public function test_requests_use_the_users_saved_language(): void
    {
        $user = User::factory()->create(['locale' => 'es']);

        $this->actingAs($user)->get('/settings/preferences')
            ->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'es'));

        $this->assertSame('es', app()->getLocale());
    }
}
