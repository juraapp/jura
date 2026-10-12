<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Notifications\LowAccountBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
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
}
