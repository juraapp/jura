<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Notifications\Notifications\BudgetThresholdReached;
use App\Modules\Notifications\Notifications\LowAccountBalance;
use App\Modules\Notifications\Notifications\PlannedPostingBlocked;
use App\Modules\Notifications\Notifications\UpcomingPlannedPayment;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Services\SeriesPoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeNotification(User $user): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Modules\\Notifications\\Notifications\\LowAccountBalance',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['message' => 'Low balance', 'severity' => 'warning', 'url' => '#'],
            'read_at' => null,
        ]);
    }

    public function test_user_can_mark_a_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->makeNotification($user);

        $response = $this->actingAs($user)->patch("/notifications/{$notification->id}/read");

        $response->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $notification = $this->makeNotification($owner);
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->patch("/notifications/{$notification->id}/read");

        $response->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $this->makeNotification($user);
        $this->makeNotification($user);

        $response = $this->actingAs($user)->patch('/notifications/read-all');

        $response->assertRedirect();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_guests_cannot_mark_notifications_as_read(): void
    {
        $response = $this->patch('/notifications/read-all');

        $response->assertRedirect('/login');
    }

    public function test_legacy_notification_types_are_renamed_by_the_module_migration(): void
    {
        $user = User::factory()->create();

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\LowAccountBalance',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_12_000000_rename_notification_types_for_modules.php');
        $migration->up();

        $this->assertDatabaseHas('notifications', ['type' => LowAccountBalance::class]);
        $this->assertDatabaseMissing('notifications', ['type' => 'App\\Notifications\\LowAccountBalance']);
    }

    public function test_a_budget_alert_for_a_bucket_whose_category_was_deleted_uses_the_bucket_name(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $bucket = Bucket::factory()->for($user)->create(['category_id' => $category->id, 'name' => 'Groceries']);
        $category->delete();

        $data = (new BudgetThresholdReached($bucket->fresh(), 90.0, false))->toArray($user);

        $this->assertStringContainsString('Groceries', $data['message']);
    }

    public function test_planned_expenses_due_within_three_days_get_one_reminder_saying_whether_they_record_themselves(): void
    {
        Carbon::setTestNow('2026-10-12 12:00:00');
        $user = User::factory()->create(['timezone' => 'UTC', 'locale' => 'en']);
        $account = Account::factory()->for($user)->create(['currency' => 'COP']);
        $archived = Account::factory()->for($user)->inactive()->create(['currency' => 'COP']);
        $plan = fn (array $attributes) => PlannedEvent::factory()->for($user)->expense()->create($attributes + ['currency' => 'COP', 'account_id' => $account->id, 'amount' => 50]);
        $auto = $plan(['date' => '2026-10-13', 'description' => 'Gym', 'auto_post' => true]);
        $manual = $plan(['date' => '2026-10-15', 'description' => 'Plumber']);
        $plan(['date' => '2026-10-16', 'description' => 'Too far']);
        $plan(['date' => '2026-10-14', 'description' => 'Archived', 'account_id' => $archived->id]);
        PlannedEvent::factory()->for($user)->income()->create(['date' => '2026-10-13', 'currency' => 'COP']);
        $other = User::factory()->create(['timezone' => 'UTC']);
        PlannedEvent::factory()->for($other)->expense()->create(['date' => '2026-10-13', 'currency' => 'COP']);

        $this->artisan('app:notify-upcoming-planned-payments')->assertSuccessful();
        $this->artisan('app:notify-upcoming-planned-payments')->assertSuccessful();

        $notes = $user->notifications()->where('type', UpcomingPlannedPayment::class)->get()->keyBy(fn ($note) => $note->data['planned_event_id']);
        $this->assertSame([$auto->id, $manual->id], $notes->keys()->sort()->values()->all());
        $this->assertStringContainsString('recorded automatically', $notes[$auto->id]->data['message']);
        $this->assertStringContainsString('Confirm it', $notes[$manual->id]->data['message']);
        $this->assertStringContainsString('section=review', $notes[$manual->id]->data['url']);
        $this->assertSame(1, $other->notifications()->count());
    }

    public function test_the_owner_is_told_once_when_an_auto_posting_plan_is_blocked(): void
    {
        Carbon::setTestNow('2026-10-12 12:00:00');
        $user = User::factory()->create(['timezone' => 'UTC', 'locale' => 'en']);
        $account = Account::factory()->for($user)->inactive()->create(['currency' => 'COP']);
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        PlannedEvent::factory()->for($user)->expense()->create([
            'date' => '2026-10-12', 'currency' => 'COP', 'account_id' => $account->id, 'category_id' => $category->id, 'auto_post' => true, 'description' => 'Gym',
        ]);
        $bystander = User::factory()->create();

        app(SeriesPoster::class)->postDue();
        app(SeriesPoster::class)->postDue();

        $notes = $user->notifications()->where('type', PlannedPostingBlocked::class)->get();
        $this->assertCount(1, $notes);
        $this->assertSame("Couldn't record Gym automatically: its account is archived or deleted.", $notes[0]->data['message']);
        $this->assertSame(0, $bystander->notifications()->count());
    }

    public function test_a_notification_from_the_hourly_job_is_written_in_its_owners_language(): void
    {
        Carbon::setTestNow('2026-10-12 12:00:00');
        app()->setLocale('en');
        $user = User::factory()->create(['timezone' => 'UTC', 'locale' => 'es']);
        $account = Account::factory()->for($user)->inactive()->create(['currency' => 'COP']);
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        PlannedEvent::factory()->for($user)->expense()->create([
            'date' => '2026-10-12', 'currency' => 'COP', 'account_id' => $account->id, 'category_id' => $category->id, 'auto_post' => true, 'description' => 'Gimnasio',
        ]);

        app(SeriesPoster::class)->postDue();

        $note = $user->notifications()->where('type', PlannedPostingBlocked::class)->sole();
        $this->assertSame('No se pudo registrar Gimnasio automáticamente: su cuenta está archivada o eliminada.', $note->data['message']);
        $this->assertSame('en', app()->getLocale());
    }

    public function test_a_saved_language_the_app_does_not_have_falls_back_to_english_for_notifications(): void
    {
        $this->assertSame('en', User::factory()->make(['locale' => 'fr'])->preferredLocale());
        $this->assertSame('es', User::factory()->make(['locale' => 'es'])->preferredLocale());
    }
}
