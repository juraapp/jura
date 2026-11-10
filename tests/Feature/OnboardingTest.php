<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_user_sees_the_wizard_with_its_options(): void
    {
        $user = User::factory()->notOnboarded()->create();

        $response = $this->actingAs($user)->get(route('onboarding.show'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Onboarding/Index')
            ->has('currencies')
            ->has('timezones')
            ->has('preferences')
            ->has('accountTypes', 5)
            ->has('accounts', 0)
            ->has('expenseCategories')
            ->whereType('salaryCategoryId', 'integer')
        );
    }

    public function test_pages_send_a_user_who_has_not_finished_the_wizard_back_to_it(): void
    {
        $user = User::factory()->notOnboarded()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('onboarding.show'));
        $this->actingAs($user)->get(route('accounts.index'))->assertRedirect(route('onboarding.show'));
    }

    public function test_wizard_steps_can_save_through_the_regular_endpoints(): void
    {
        $user = User::factory()->notOnboarded()->create();

        $this->actingAs($user)
            ->from(route('onboarding.show'))
            ->post(route('accounts.store'), [
                'name' => 'Main bank', 'type' => 'bank', 'initial_balance' => 1000, 'currency' => 'COP', 'color' => '#146a50',
            ])
            ->assertRedirect(route('onboarding.show'));

        $this->assertDatabaseHas('accounts', ['user_id' => $user->id, 'name' => 'Main bank']);
    }

    public function test_finishing_or_skipping_marks_the_wizard_done_for_good(): void
    {
        $user = User::factory()->notOnboarded()->create();

        $this->actingAs($user)->post(route('onboarding.complete'))->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->onboarding_completed_at);
        $this->actingAs($user)->get(route('onboarding.show'))->assertRedirect(route('dashboard'));
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_an_onboarded_user_goes_straight_to_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('onboarding.show'))->assertRedirect(route('dashboard'));
    }

    public function test_guests_cannot_open_the_wizard(): void
    {
        $this->get(route('onboarding.show'))->assertRedirect(route('login'));
        $this->post(route('onboarding.complete'))->assertRedirect(route('login'));
    }
}
