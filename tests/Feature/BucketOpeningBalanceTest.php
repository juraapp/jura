<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BucketOpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function savingsPayload(array $overrides = []): array
    {
        return array_merge(['kind' => 'savings', 'name' => 'Trip', 'currency' => 'COP'], $overrides);
    }

    private function funded(Bucket $bucket): string
    {
        return number_format((float) Allocation::where('bucket_id', $bucket->id)->sum('amount'), 2, '.', '');
    }

    public function test_a_bucket_can_be_created_with_an_opening_balance(): void
    {
        $this->actingAs($this->user)->post('/planning/buckets', $this->savingsPayload(['opening_balance' => 750]))
            ->assertRedirect()->assertSessionHasNoErrors();

        $bucket = Bucket::firstOrFail();
        $this->assertSame('750.00', $this->funded($bucket));
        $this->assertSame(1, Allocation::where('source', AllocationSource::Opening->value)->count());
    }

    public function test_editing_the_opening_balance_updates_the_same_row_and_zero_removes_it(): void
    {
        $bucket = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP', 'name' => 'Trip']);

        $this->actingAs($this->user)->patch("/planning/buckets/{$bucket->id}", $this->savingsPayload(['opening_balance' => 500]))->assertRedirect();
        $this->actingAs($this->user)->patch("/planning/buckets/{$bucket->id}", $this->savingsPayload(['opening_balance' => 900]))->assertRedirect();

        $this->assertSame(1, Allocation::where('bucket_id', $bucket->id)->count());
        $this->assertSame('900.00', $this->funded($bucket));

        $this->actingAs($this->user)->patch("/planning/buckets/{$bucket->id}", $this->savingsPayload(['opening_balance' => 0]))->assertRedirect();

        $this->assertSame(0, Allocation::where('bucket_id', $bucket->id)->count());
    }

    public function test_editing_a_bucket_without_the_field_keeps_its_opening_balance(): void
    {
        $bucket = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP', 'name' => 'Trip']);
        $this->actingAs($this->user)->patch("/planning/buckets/{$bucket->id}", $this->savingsPayload(['opening_balance' => 500]));

        $this->actingAs($this->user)->patch("/planning/buckets/{$bucket->id}", $this->savingsPayload(['name' => 'Renamed']))->assertRedirect();

        $this->assertSame('500.00', $this->funded($bucket));
    }

    public function test_funds_can_be_removed_down_to_zero_even_when_spending_lowers_what_is_available(): void
    {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $account = Account::factory()->for($this->user)->create(['currency' => 'COP']);
        $bucket = Bucket::factory()->for($this->user)->create(['category_id' => $category->id, 'cap_amount' => 1000, 'currency' => 'COP']);
        Allocation::factory()->for($this->user)->for($bucket)->create(['amount' => 300, 'currency' => 'COP']);
        Transaction::factory()->for($this->user)->for($account)->create(['category_id' => $category->id, 'type' => 'expense', 'amount' => 500, 'date' => today()]);

        $this->actingAs($this->user)->post("/planning/buckets/{$bucket->id}/adjust", ['amount' => -300])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('0.00', $this->funded($bucket));

        $this->actingAs($this->user)->post("/planning/buckets/{$bucket->id}/adjust", ['amount' => -1])
            ->assertSessionHasErrors('amount');
    }

    public function test_reset_to_zero_removes_all_funded_money(): void
    {
        $bucket = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP']);
        Allocation::factory()->for($this->user)->for($bucket)->create(['amount' => 800, 'currency' => 'COP']);

        $this->actingAs($this->user)->post("/planning/buckets/{$bucket->id}/reset")->assertRedirect()->assertSessionHas('success');

        $this->assertSame('0.00', $this->funded($bucket));
    }

    public function test_the_history_lists_every_allocation_and_marks_which_ones_can_be_removed(): void
    {
        $bucket = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP']);
        Allocation::factory()->for($this->user)->for($bucket)->count(7)->create(['source' => AllocationSource::Adjustment, 'currency' => 'COP']);
        $income = Allocation::factory()->for($this->user)->for($bucket)->create(['source' => AllocationSource::Income, 'source_id' => 99, 'currency' => 'COP']);

        $rows = $this->actingAs($this->user)->getJson("/planning/buckets/{$bucket->id}/allocations")->assertOk()->json('allocations');

        $this->assertCount(8, $rows);
        $this->assertFalse(collect($rows)->firstWhere('id', $income->id)['deletable']);
        $this->assertCount(7, collect($rows)->where('deletable', true));
    }

    public function test_a_manual_allocation_can_be_deleted_but_income_derived_ones_cannot(): void
    {
        $bucket = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP']);
        $manual = Allocation::factory()->for($this->user)->for($bucket)->create(['source' => AllocationSource::Adjustment, 'currency' => 'COP']);
        $income = Allocation::factory()->for($this->user)->for($bucket)->create(['source' => AllocationSource::Income, 'source_id' => 5, 'currency' => 'COP']);

        $this->actingAs($this->user)->delete("/planning/buckets/{$bucket->id}/allocations/{$manual->id}")->assertRedirect()->assertSessionHas('success');
        $this->actingAs($this->user)->delete("/planning/buckets/{$bucket->id}/allocations/{$income->id}")->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('allocations', ['id' => $manual->id]);
        $this->assertDatabaseHas('allocations', ['id' => $income->id]);
    }

    public function test_deleting_one_side_of_a_move_removes_the_other_side_too(): void
    {
        $from = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP']);
        $to = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP']);
        Allocation::factory()->for($this->user)->for($from)->create(['amount' => 1000, 'currency' => 'COP']);
        $this->actingAs($this->user)->post("/planning/buckets/{$from->id}/move", ['to_bucket_id' => $to->id, 'amount' => 400])->assertRedirect();
        $received = Allocation::where('bucket_id', $to->id)->firstOrFail();

        $this->actingAs($this->user)->delete("/planning/buckets/{$to->id}/allocations/{$received->id}")->assertRedirect()->assertSessionHas('success');

        $this->assertSame('1000.00', $this->funded($from));
        $this->assertSame('0.00', $this->funded($to));
    }

    public function test_other_users_allocations_cannot_be_reached(): void
    {
        $foreign = Allocation::factory()->create(['source' => AllocationSource::Adjustment]);
        $own = Bucket::factory()->savings()->for($this->user)->create();

        $this->actingAs($this->user)->delete("/planning/buckets/{$foreign->bucket_id}/allocations/{$foreign->id}")->assertNotFound();
        $this->actingAs($this->user)->getJson("/planning/buckets/{$foreign->bucket_id}/allocations")->assertNotFound();
        $this->actingAs($this->user)->delete("/planning/buckets/{$own->id}/allocations/{$foreign->id}")->assertNotFound();
        $this->assertDatabaseHas('allocations', ['id' => $foreign->id]);
    }
}
