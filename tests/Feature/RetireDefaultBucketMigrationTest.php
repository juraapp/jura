<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RetireDefaultBucketMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_30_201426_retire_default_buckets.php');
    }

    public function test_default_buckets_are_removed_and_everything_pointing_at_them_goes_to_ready_to_assign(): void
    {
        $this->migration()->down();

        $user = User::factory()->create();
        Account::factory()->for($user)->create(['currency' => 'COP', 'initial_balance' => 0]);
        $default = Bucket::factory()->savings()->for($user)->create(['name' => 'Unallocated']);
        DB::table('buckets')->where('id', $default->id)->update(['is_default' => true]);
        $trip = Bucket::factory()->savings()->for($user)->create(['name' => 'Trip']);
        $groceries = Bucket::factory()->for($user)->create(['carry_over' => 'overflow', 'overflow_bucket_id' => $default->id]);
        $stepUp = Bucket::factory()->savings()->for($user)->create(['fill_rule' => 'fixed', 'fill_value' => 10, 'step_up_value' => 20, 'step_up_after_bucket_id' => $default->id]);
        $stray = Bucket::factory()->savings()->for($user)->create(['carry_over' => 'overflow', 'overflow_bucket_id' => null]);

        $row = fn (Bucket $bucket, int $amount, string $source, ?int $sourceId) => Allocation::factory()->for($user)->for($bucket)
            ->create(['amount' => $amount, 'currency' => 'COP', 'source' => $source, 'source_id' => $sourceId]);
        $row($default, 900, 'income', 77);
        $row($default, -300, 'transfer', $trip->id);
        $moveIn = $row($trip, 300, 'transfer', $default->id);
        $row($groceries, -50, 'carry', $default->id);
        $carryIn = $row($trip, 50, 'carry', $groceries->id);
        $released = $row($trip, 20, 'carry', $default->id);
        $spend = $row($default, -100, 'spend', 99);

        $item = WishlistItem::factory()->for($user)->create(['bucket_id' => $default->id, 'currency' => 'COP', 'purchase_allocation_id' => $spend->id]);
        $plan = PlannedEvent::factory()->for($user)->income()->create(['allocation_overrides' => [
            ['bucket_id' => $default->id, 'amount' => 100], ['bucket_id' => $trip->id, 'amount' => 200],
        ]]);
        $onlyDefault = PlannedEvent::factory()->for($user)->income()->create(['allocation_overrides' => [['bucket_id' => $default->id, 'amount' => 100]]]);

        $this->migration()->up();

        $this->assertFalse(Schema::hasColumn('buckets', 'is_default'));
        $this->assertNull(Bucket::withoutGlobalScopes()->find($default->id));
        $this->assertSame(0, Allocation::withoutGlobalScopes()->where('bucket_id', $default->id)->count());

        $groceries->refresh();
        $this->assertNull($groceries->overflow_bucket_id);
        $this->assertSame('release', $groceries->carry_over->value);
        $this->assertNull($stepUp->fresh()->step_up_after_bucket_id);
        $this->assertNull($stepUp->fresh()->step_up_value);
        $this->assertSame('release', $stray->fresh()->carry_over->value);

        $this->assertSame(['adjustment', null], [$moveIn->fresh()->source->value, $moveIn->fresh()->source_id]);
        $this->assertSame(['ready', null], [$released->fresh()->source->value, $released->fresh()->source_id]);
        $this->assertSame(['carry', $groceries->id], [$carryIn->fresh()->source->value, $carryIn->fresh()->source_id], 'moves between real buckets are kept');

        $item->refresh();
        $this->assertNull($item->bucket_id);
        $this->assertNull($item->purchase_allocation_id);
        $this->assertEquals([['bucket_id' => $trip->id, 'amount' => 200]], $plan->fresh()->allocation_overrides);
        $this->assertNull($onlyDefault->fresh()->allocation_overrides);
    }
}
