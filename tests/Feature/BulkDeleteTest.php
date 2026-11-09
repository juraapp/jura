<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Enums\PlannedEventStatus;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\PlannedEventSeries;
use App\Modules\Planning\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_planned_events_are_deleted_in_bulk_and_series_occurrences_are_remembered(): void
    {
        $series = PlannedEventSeries::factory()->for($this->user)->create(['starts_on' => now()->toDateString(), 'ends_on' => now()->addMonths(3)->toDateString()]);
        $this->artisan('planning:extend-series');
        $occurrences = $series->events()->orderBy('date')->take(2)->get();
        $single = PlannedEvent::factory()->for($this->user)->create();
        $foreign = PlannedEvent::factory()->create();

        $this->actingAs($this->user)->post('/planning/cash-flow/bulk-delete', ['ids' => [$occurrences[0]->id, $occurrences[1]->id, $single->id, $foreign->id]])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('planned_events', ['id' => $single->id]);
        $this->assertDatabaseMissing('planned_events', ['id' => $occurrences[0]->id]);
        $this->assertDatabaseHas('planned_events', ['id' => $foreign->id]);
        $this->assertCount(2, $series->fresh()->excluded_dates);
        $this->artisan('planning:extend-series');
        $this->assertDatabaseMissing('planned_events', ['id' => $occurrences[1]->id]);
    }

    public function test_bulk_delete_keeps_realized_plans(): void
    {
        $realized = PlannedEvent::factory()->for($this->user)->status(PlannedEventStatus::Realized)->create();
        $open = PlannedEvent::factory()->for($this->user)->create();

        $this->actingAs($this->user)->post('/planning/cash-flow/bulk-delete', ['ids' => [$realized->id, $open->id]])->assertSessionHas('success');

        $this->assertDatabaseHas('planned_events', ['id' => $realized->id]);
        $this->assertDatabaseMissing('planned_events', ['id' => $open->id]);
    }

    public function test_wishlist_items_are_deleted_in_bulk(): void
    {
        $items = WishlistItem::factory()->for($this->user)->count(2)->create();
        $foreignItem = WishlistItem::factory()->create();

        $this->actingAs($this->user)->post('/planning/wishlist/bulk-delete', ['ids' => [...$items->pluck('id'), $foreignItem->id]])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(0, WishlistItem::where('user_id', $this->user->id)->count());
        $this->assertNotNull(WishlistItem::withoutGlobalScopes()->find($foreignItem->id));
    }

    public function test_buckets_are_deleted_in_bulk_with_their_money(): void
    {
        $one = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP']);
        $two = Bucket::factory()->savings()->for($this->user)->create(['currency' => 'COP']);
        Allocation::factory()->for($this->user)->for($one)->create(['amount' => 300, 'currency' => 'COP']);

        $this->actingAs($this->user)->post('/planning/buckets/bulk-delete', ['ids' => [$one->id, $two->id]])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(0, Bucket::where('user_id', $this->user->id)->count());
        $this->assertSame(0, Allocation::where('user_id', $this->user->id)->count(), 'what they held is ready to assign again');
    }

    public function test_the_ids_are_required_and_limited_on_every_bulk_endpoint(): void
    {
        foreach (['planning/cash-flow', 'planning/wishlist', 'planning/recurring', 'planning/buckets'] as $path) {
            $this->actingAs($this->user)->post("/{$path}/bulk-delete", [])->assertSessionHasErrors('ids');
            $this->actingAs($this->user)->post("/{$path}/bulk-delete", ['ids' => range(1, 501)])->assertSessionHasErrors('ids');
        }
    }

    public function test_guests_cannot_bulk_delete(): void
    {
        $this->post('/planning/wishlist/bulk-delete', ['ids' => [1]])->assertRedirect('/login');
    }
}
