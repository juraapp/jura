<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Planning\Contracts\MoneyMap;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Putting more in buckets than is ready to assign asks for confirmation first.
 */
class OverAssignTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Bucket $bucket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['currency_default' => 'COP']);
        Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 1000]);
        $this->bucket = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP', 'name' => 'Trip']);
        $this->actingAs($this->user);
    }

    private function ready(): string
    {
        return app(MoneyMap::class)->readyToAssign($this->user, 'COP')->toDecimalString();
    }

    public function test_adding_more_than_is_ready_asks_for_confirmation_and_then_over_assigns(): void
    {
        $this->post("/planning/buckets/{$this->bucket->id}/adjust", ['amount' => 1200])->assertSessionHasErrors('over_assign');
        $this->assertSame(0, Allocation::count());

        $this->post("/planning/buckets/{$this->bucket->id}/adjust", ['amount' => 1000])->assertSessionHasNoErrors();
        $this->post("/planning/buckets/{$this->bucket->id}/adjust", ['amount' => 200, 'confirm_over_assign' => true])->assertSessionHasNoErrors();

        $this->assertSame('-200.00', $this->ready());
    }

    public function test_an_opening_balance_only_checks_what_it_adds(): void
    {
        $this->post('/planning/buckets', ['kind' => 'savings', 'name' => 'Car', 'currency' => 'COP', 'opening_balance' => 1500])
            ->assertSessionHasErrors('over_assign');
        $this->assertSame(1, Bucket::count());

        $this->post('/planning/buckets', ['kind' => 'savings', 'name' => 'Car', 'currency' => 'COP', 'opening_balance' => 900])->assertSessionHasNoErrors();
        $car = Bucket::where('name', 'Car')->firstOrFail();

        $this->patch("/planning/buckets/{$car->id}", ['kind' => 'savings', 'name' => 'Car', 'currency' => 'COP', 'opening_balance' => 1000])
            ->assertSessionHasNoErrors();
        $this->patch("/planning/buckets/{$car->id}", ['kind' => 'savings', 'name' => 'Car', 'currency' => 'COP', 'opening_balance' => 1001])
            ->assertSessionHasErrors('over_assign');
        $this->assertSame('0.00', $this->ready());
    }

    public function test_money_can_move_between_a_bucket_and_ready_to_assign(): void
    {
        $this->post("/planning/buckets/{$this->bucket->id}/move", ['from_ready' => true, 'amount' => 1100])->assertSessionHasErrors('over_assign');
        $this->post("/planning/buckets/{$this->bucket->id}/move", ['from_ready' => true, 'amount' => 600])->assertSessionHasNoErrors();
        $this->assertSame('400.00', $this->ready());

        $this->post("/planning/buckets/{$this->bucket->id}/move", ['to_bucket_id' => 'ready', 'amount' => 700])->assertSessionHasErrors('amount');
        $this->post("/planning/buckets/{$this->bucket->id}/move", ['to_bucket_id' => 'ready', 'amount' => 250])->assertSessionHasNoErrors();

        $this->assertSame('650.00', $this->ready());
        $this->assertSame(2, Allocation::where('source', AllocationSource::Adjustment->value)->count());
    }
}
