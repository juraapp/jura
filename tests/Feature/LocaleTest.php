<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_sees_the_language_saved_in_the_cookie(): void
    {
        $this->withCookie('locale', 'es')
            ->get('/login')
            ->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'es'));
    }

    public function test_a_guest_without_the_cookie_gets_the_browser_language(): void
    {
        $this->withHeader('Accept-Language', 'es-CO,es;q=0.9,en;q=0.8')
            ->get('/login')
            ->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'es'));

        $this->withHeader('Accept-Language', 'fr-FR')
            ->get('/login')
            ->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'en'));
    }

    public function test_a_guest_can_switch_the_language_and_it_is_remembered_in_a_cookie(): void
    {
        $this->from('/login')
            ->put('/locale', ['locale' => 'es'])
            ->assertRedirect('/login')
            ->assertCookie('locale', 'es');
    }

    public function test_only_the_apps_languages_can_be_chosen(): void
    {
        $this->from('/login')
            ->put('/locale', ['locale' => 'fr'])
            ->assertSessionHasErrors('locale')
            ->assertCookieMissing('locale');
    }

    public function test_switching_while_signed_in_saves_the_users_language(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->from('/onboarding')
            ->put('/locale', ['locale' => 'es'])
            ->assertCookie('locale', 'es');

        $this->assertSame('es', $user->fresh()->locale);
    }

    public function test_a_signed_in_users_language_wins_over_the_cookie_and_is_copied_into_it(): void
    {
        $user = User::factory()->create(['locale' => 'es']);

        $this->actingAs($user)->withCookie('locale', 'en')
            ->get('/settings/preferences')
            ->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'es'))
            ->assertCookie('locale', 'es');
    }

    public function test_saving_preferences_updates_the_cookie(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->withCookie('locale', 'en')
            ->patch('/settings/preferences', [
                'currency_default' => 'USD',
                'locale' => 'es',
                'timezone' => 'UTC',
                'date_format' => 'auto',
                'time_format' => 'auto',
                'currency_display' => 'auto',
                'currency_position' => 'auto',
            ])
            ->assertSessionHasNoErrors()
            ->assertCookie('locale', 'es');
    }

    public function test_registering_keeps_the_language_chosen_before_signing_up(): void
    {
        $this->withCookie('locale', 'es')->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertSame('es', User::where('email', 'test@example.com')->value('locale'));
    }
}
