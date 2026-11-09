<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Planning\Contracts\MoneyMap;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Savings goals become savings buckets. The legacy tables are recreated here (the migration drops them), and every
 * expected number is worked out by hand in the comments.
 */
class MigrateSavingsGoalsToBucketsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 09:00:00');
        Schema::dropIfExists('savings_goal_contributions');
        Schema::dropIfExists('savings_goals');
        Schema::create('savings_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('target_amount', 15, 2);
            $table->char('currency', 3)->default('COP');
            $table->date('target_date')->nullable();
            $table->string('icon', 32)->default('flag');
            $table->string('color', 7)->default('#2563eb');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('savings_goal_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_goal_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->unsignedBigInteger('linked_transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $this->user = User::factory()->create(['timezone' => 'UTC', 'locale' => 'en']);
        $this->account = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 1000000]);
    }

    protected function tearDown(): void
    {
        // The legacy tables were created and dropped with DDL, which commits: remove what this test left behind.
        DB::table('notifications')->delete();
        DB::table('users')->delete();
        Schema::dropIfExists('savings_goal_contributions');
        Schema::dropIfExists('savings_goals');

        parent::tearDown();
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_11_09_203230_migrate_savings_goals_to_buckets.php');
    }

    private function goal(array $attributes = [], array $contributions = []): int
    {
        $id = DB::table('savings_goals')->insertGetId($attributes + [
            'user_id' => $this->user->id, 'name' => 'Car', 'target_amount' => 5000000, 'currency' => 'COP', 'target_date' => '2027-06-30',
            'status' => 'active', 'created_at' => '2026-08-01 10:00:00', 'updated_at' => '2026-10-05 10:00:00', 'deleted_at' => null,
        ]);

        foreach ($contributions as [$amount, $date, $notes]) {
            DB::table('savings_goal_contributions')->insert([
                'savings_goal_id' => $id, 'amount' => $amount, 'date' => $date, 'notes' => $notes, 'created_at' => $date.' 08:00:00', 'updated_at' => $date.' 08:00:00',
            ]);
        }

        return $id;
    }

    private function bucket(string $name): Bucket
    {
        return Bucket::withoutGlobalScopes()->where('name', $name)->sole();
    }

    private function readyToAssign(): string
    {
        return app(MoneyMap::class)->readyToAssign($this->user->fresh(), 'COP')->toDecimalString();
    }

    public function test_an_active_goal_becomes_a_savings_bucket_holding_its_contributions(): void
    {
        $this->goal(contributions: [[200000, '2026-09-01', 'First'], [300000, '2026-10-01', null]]);

        $this->migration()->up();

        $bucket = $this->bucket('Car');
        $this->assertSame(
            ['savings', 'COP', '5000000.00', '2027-06-30', 100, 'carry', '2026-08-01', '2026-09-01', null, false],
            [$bucket->kind->value, $bucket->currency, $bucket->cap_amount->toDecimalString(), $bucket->target_date->toDateString(), $bucket->priority,
                $bucket->carry_over->value, $bucket->tracking_started_on->toDateString(), $bucket->funded_since->toDateString(), $bucket->completed_on, $bucket->completes_on_purchase],
        );
        $rows = Allocation::withoutGlobalScopes()->where('bucket_id', $bucket->id)->orderBy('date')->get();
        $this->assertSame(
            [['200000.00', '2026-09-01', 'adjustment', 'First'], ['300000.00', '2026-10-01', 'adjustment', 'Savings goal contribution']],
            $rows->map(fn (Allocation $row) => [$row->amount->toDecimalString(), $row->date->toDateString(), $row->source->value, $row->description])->all(),
        );
        // 1,000,000 in the account and 200,000 + 300,000 set aside.
        $this->assertSame('500000.00', $this->readyToAssign());
    }

    public function test_a_completed_goal_keeps_its_history_but_holds_nothing(): void
    {
        $this->goal(['name' => 'Laptop', 'target_amount' => 2000000, 'status' => 'completed', 'updated_at' => '2026-10-05 10:00:00'], [[2000000, '2026-07-01', null]]);

        $this->migration()->up();

        $bucket = $this->bucket('Laptop');
        $this->assertSame('2026-10-05', $bucket->completed_on->toDateString());
        $rows = Allocation::withoutGlobalScopes()->where('bucket_id', $bucket->id)->orderBy('id')->get();
        // +2,000,000 on Jul 1, then released back to ready to assign on Oct 5: net nothing is set aside.
        $this->assertSame(
            [['2000000.00', 'adjustment', '2026-07-01'], ['-2000000.00', 'ready', '2026-10-05']],
            $rows->map(fn (Allocation $row) => [$row->amount->toDecimalString(), $row->source->value, $row->date->toDateString()])->all(),
        );
        $this->assertSame('1000000.00', $this->readyToAssign());
    }

    public function test_an_archived_goal_without_contributions_is_a_completed_bucket_with_no_history(): void
    {
        $this->goal(['name' => 'Old', 'status' => 'archived', 'updated_at' => '2026-09-09 10:00:00']);

        $this->migration()->up();

        $bucket = $this->bucket('Old');
        $this->assertSame(['2026-09-09', 0, null], [$bucket->completed_on->toDateString(), Allocation::withoutGlobalScopes()->where('bucket_id', $bucket->id)->count(), $bucket->funded_since]);
    }

    public function test_a_goal_in_the_trash_is_not_migrated(): void
    {
        $this->goal(['name' => 'Trashed', 'deleted_at' => '2026-10-20 10:00:00'], [[100000, '2026-09-01', null]]);

        $this->migration()->up();

        $this->assertSame(0, Bucket::withoutGlobalScopes()->where('name', 'Trashed')->count());
        $this->assertSame(0, Allocation::withoutGlobalScopes()->count());
    }

    public function test_each_new_bucket_gets_the_next_priority_in_its_currency(): void
    {
        $this->goal(['name' => 'First']);
        $this->goal(['name' => 'Second']);
        $this->goal(['name' => 'Dollars', 'currency' => 'USD']);

        $this->migration()->up();

        $this->assertSame([100, 110, 100], [$this->bucket('First')->priority, $this->bucket('Second')->priority, $this->bucket('Dollars')->priority]);
    }

    public function test_behind_schedule_alerts_in_the_bell_point_at_the_bucket(): void
    {
        $goal = $this->goal(contributions: [[100000, '2026-09-01', null]]);
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id, 'type' => 'App\\Modules\\Notifications\\Notifications\\GoalBehindSchedule', 'notifiable_type' => User::class, 'notifiable_id' => $this->user->id,
            'data' => json_encode(['type' => 'goal_behind', 'severity' => 'warning', 'message' => 'Your "Car" goal is falling behind its target date.', 'savings_goal_id' => $goal, 'url' => 'http://localhost/planning/goals']),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->migration()->up();

        $row = DB::table('notifications')->where('id', $id)->first();
        $data = json_decode($row->data, true);
        $this->assertSame('App\\Modules\\Notifications\\Notifications\\BucketBehindSchedule', $row->type);
        $this->assertSame([$this->bucket('Car')->id, 'bucket_behind', false], [$data['bucket_id'], $data['type'], array_key_exists('savings_goal_id', $data)]);
        $this->assertStringEndsWith('/planning/buckets', $data['url']);
    }

    public function test_users_whose_goals_put_money_in_buckets_get_one_notice_in_their_language(): void
    {
        $spanish = User::factory()->create(['timezone' => 'UTC', 'locale' => 'es']);
        $none = User::factory()->create(['timezone' => 'UTC']);
        $this->goal(contributions: [[100000, '2026-09-01', null]]);
        $this->goal(['name' => 'Another'], [[50000, '2026-09-02', null]]);
        $this->goal(['user_id' => $spanish->id, 'name' => 'Viaje'], [[70000, '2026-09-03', null]]);
        $this->goal(['user_id' => $none->id, 'name' => 'Done', 'status' => 'completed'], [[10000, '2026-09-03', null]]);

        $this->migration()->up();

        $notices = DB::table('notifications')->where('type', 'App\\Modules\\Notifications\\Notifications\\BucketsFromGoals')->get()->keyBy('notifiable_id');
        $this->assertSame([$this->user->id, $spanish->id], $notices->keys()->sort()->values()->all(), 'one each, none for a user with only completed goals');
        $this->assertStringContainsString('Your savings goals are now savings buckets', json_decode($notices[$this->user->id]->data, true)['message']);
        $this->assertStringContainsString('Tus metas de ahorro ahora son baldes', json_decode($notices[$spanish->id]->data, true)['message']);
        $this->assertSame('Aporte a la meta de ahorro', Allocation::withoutGlobalScopes()->where('bucket_id', $this->bucket('Viaje')->id)->value('description'));
    }

    public function test_the_goal_tables_are_dropped_and_running_it_again_does_nothing(): void
    {
        $this->goal(contributions: [[100000, '2026-09-01', null]]);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertFalse(Schema::hasTable('savings_goals'));
        $this->assertFalse(Schema::hasTable('savings_goal_contributions'));
        $this->assertSame(1, Bucket::withoutGlobalScopes()->count());
    }

    public function test_it_cannot_be_undone(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->migration()->down();
    }
}
