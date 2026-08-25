<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeNotification(User $user): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\LowAccountBalance',
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
}
