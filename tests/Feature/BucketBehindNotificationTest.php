<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Notifications\Notifications\BucketBehindSchedule;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The weekly alert for savings buckets that fall behind their target date. A bucket made on Sep 1 with a target of
 * 1,000,000 by Jan 12 is expected to hold 30.8% today (Oct 12); 100,000 is 10% (behind), 400,000 is 40% (on track).
 */
class BucketBehindNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-12 09:00:00');
    }

    private function user(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['currency_default' => 'COP']);
        Account::factory()->for($user)->create(['currency' => 'COP', 'initial_balance' => 1000000]);

        return $user;
    }

    private function bucket(User $user, string $name, int $saved, array $attributes = []): Bucket
    {
        $bucket = Bucket::factory()->savings()->for($user)->create($attributes + [
            'name' => $name, 'currency' => 'COP', 'cap_amount' => 1000000, 'target_date' => '2027-01-12', 'created_at' => '2026-09-01',
        ]);
        Allocation::factory()->for($user)->for($bucket)->create(['amount' => $saved, 'currency' => 'COP']);

        return $bucket;
    }

    private function run_(): void
    {
        $this->artisan('app:notify-behind-savings-buckets')->assertSuccessful();
    }

    public function test_a_bucket_behind_its_pace_notifies_its_owner_once_until_it_is_read(): void
    {
        $user = $this->user();
        $bucket = $this->bucket($user, 'Trip', 100000);

        $this->run_();
        $this->run_();

        $this->assertSame(1, $user->unreadNotifications()->count());
        $notification = $user->unreadNotifications()->first();
        $this->assertSame(BucketBehindSchedule::class, $notification->type);
        $this->assertSame([$bucket->id, 'bucket_behind', 'Your "Trip" savings bucket is falling behind its target date.'], [
            $notification->data['bucket_id'], $notification->data['type'], $notification->data['message'],
        ]);

        $notification->markAsRead();
        $this->run_();

        $this->assertSame(1, $user->unreadNotifications()->count(), 'read, so it may warn again');
    }

    public function test_buckets_on_track_overdue_completed_or_without_a_target_do_not_notify(): void
    {
        $user = $this->user();
        $this->bucket($user, 'On track', 400000);
        $this->bucket($user, 'Overdue', 100000, ['target_date' => '2026-10-01']);
        $this->bucket($user, 'Completed', 100000, ['completed_on' => '2026-10-01']);
        $this->bucket($user, 'No date', 100000, ['target_date' => null]);

        $this->run_();

        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_each_owner_is_told_about_their_own_buckets_only(): void
    {
        $mine = $this->user();
        $theirs = $this->user();
        $this->bucket($mine, 'Mine', 100000);
        $this->bucket($theirs, 'Theirs', 400000);

        $this->run_();

        $this->assertSame(['Mine'], $mine->notifications->map(fn ($notification) => $notification->data['message'])->map(fn (string $message) => explode('"', $message)[1])->all());
        $this->assertSame(0, $theirs->notifications()->count());
    }

    public function test_the_alert_is_written_in_the_owners_language(): void
    {
        $user = $this->user(['locale' => 'es']);
        $this->bucket($user, 'Viaje', 100000);

        $this->run_();

        $this->assertSame('Tu balde de ahorro "Viaje" se está atrasando respecto a su fecha objetivo.', $user->notifications()->first()->data['message']);
    }
}
