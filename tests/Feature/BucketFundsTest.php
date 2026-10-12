<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BucketFundsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Bucket $from;

    private Bucket $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->from = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP', 'name' => 'Car']);
        $this->to = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP', 'name' => 'Trip']);
        Allocation::factory()->for($this->user)->for($this->from)->create(['amount' => 1000, 'currency' => 'COP']);
    }

    private function balance(Bucket $bucket): float
    {
        return (float) Allocation::where('bucket_id', $bucket->id)->sum('amount');
    }

    public function test_money_moves_between_two_buckets_as_a_pair_of_allocations(): void
    {
        $this->actingAs($this->user)
            ->post("/planning/buckets/{$this->from->id}/move", ['to_bucket_id' => $this->to->id, 'amount' => 400, 'description' => 'Trip deposit'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(600.0, $this->balance($this->from));
        $this->assertSame(400.0, $this->balance($this->to));
        $this->assertSame(2, Allocation::where('source', AllocationSource::Transfer->value)->count());
    }

    public function test_moving_more_than_is_available_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post("/planning/buckets/{$this->from->id}/move", ['to_bucket_id' => $this->to->id, 'amount' => 1000.01])
            ->assertSessionHasErrors('amount');

        $this->assertSame(1000.0, $this->balance($this->from));
        $this->assertSame(0.0, $this->balance($this->to));
    }

    public function test_the_target_must_be_a_different_bucket_of_the_same_currency_owned_by_the_user(): void
    {
        $usd = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'USD']);
        $foreign = Bucket::factory()->savings()->create(['currency' => 'COP']);

        $this->actingAs($this->user);

        foreach ([$this->from->id, $usd->id, $foreign->id, 999999] as $target) {
            $this->post("/planning/buckets/{$this->from->id}/move", ['to_bucket_id' => $target, 'amount' => 10])
                ->assertSessionHasErrors('to_bucket_id');
        }

        $this->assertDatabaseCount('allocations', 1);
    }

    public function test_funds_can_be_added_or_removed_manually(): void
    {
        $this->actingAs($this->user)
            ->post("/planning/buckets/{$this->from->id}/adjust", ['amount' => 250, 'description' => 'Gift'])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame(1250.0, $this->balance($this->from));

        $this->post("/planning/buckets/{$this->from->id}/adjust", ['amount' => -50])->assertRedirect();
        $this->assertSame(1200.0, $this->balance($this->from));

        $adjustment = Allocation::where('source', AllocationSource::Adjustment->value)->where('description', 'Gift')->firstOrFail();
        $this->assertSame('250.00', $adjustment->amount->toDecimalString());
    }

    public function test_an_adjustment_cannot_take_more_than_is_available_and_cannot_be_zero(): void
    {
        $this->actingAs($this->user)
            ->post("/planning/buckets/{$this->from->id}/adjust", ['amount' => -1000.01])
            ->assertSessionHasErrors('amount');
        $this->post("/planning/buckets/{$this->from->id}/adjust", ['amount' => 0])->assertSessionHasErrors('amount');

        $this->assertSame(1000.0, $this->balance($this->from));
    }

    public function test_other_users_buckets_cannot_be_touched(): void
    {
        $intruder = User::factory()->create();

        $this->actingAs($intruder);
        $this->post("/planning/buckets/{$this->from->id}/move", ['to_bucket_id' => $this->to->id, 'amount' => 1])->assertNotFound();
        $this->post("/planning/buckets/{$this->from->id}/adjust", ['amount' => 1])->assertNotFound();
    }
}
