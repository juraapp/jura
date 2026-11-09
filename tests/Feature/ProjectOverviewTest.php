<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\Certainty;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Enums\WishlistStatus;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\Project;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Planning\Services\ProjectOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The per-project numbers (planned, funded, spent, budget left, target-date pace, projected completion). Every
 * expected value is worked out by hand in the comments.
 */
class ProjectOverviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-12 09:00:00');
        $this->user = User::factory()->create(['currency_default' => 'COP']);
        $this->account = Account::factory()->for($this->user)->create(['currency' => 'COP', 'initial_balance' => 1000000, 'created_at' => '2026-01-01']);
        $this->actingAs($this->user);
    }

    /**
     * @return array<string, mixed> the entry of the project with this name (null: the items in none)
     */
    private function entry(?string $name): array
    {
        foreach (app(ProjectOverview::class)->index($this->user, ForecastView::Expected, 24) as $entry) {
            if ($entry['name'] === $name) {
                return $entry;
            }
        }

        $this->fail('No such project entry: '.var_export($name, true));
    }

    private function item(array $attributes): WishlistItem
    {
        return WishlistItem::factory()->for($this->user)->create($attributes + ['currency' => 'COP', 'importance' => 3]);
    }

    private function savings(string $name, int $funded): Bucket
    {
        $bucket = Bucket::factory()->savings()->for($this->user)->create(['name' => $name, 'currency' => 'COP']);
        Allocation::factory()->for($this->user)->for($bucket)->create(['amount' => $funded, 'currency' => 'COP']);

        return $bucket;
    }

    public function test_items_sharing_a_bucket_are_covered_once(): void
    {
        // The pot holds 100,000. A (80,000, wanted 5) takes 80,000 and B (60,000, wanted 3) the 20,000 left: 100,000
        // of 140,000 is 71.4%. (Counting the pot for each item gave 80,000 + 60,000 = 100%.)
        $pot = $this->savings('Pot', 100000);
        $this->item(['name' => 'A', 'price' => 80000, 'importance' => 5, 'bucket_id' => $pot->id]);
        $this->item(['name' => 'B', 'price' => 60000, 'importance' => 3, 'bucket_id' => $pot->id]);

        $totals = $this->entry(null)['totals'][0];

        $this->assertSame([140000.0, 100000.0, 71.4], [$totals['wanted'], $totals['funded'], $totals['fundedPercent']]);
    }

    public function test_a_projects_bucket_covers_its_items_without_a_bucket(): void
    {
        // The garage fund holds 150,000: X (100,000, wanted 4) is covered in full, Y (100,000, wanted 2) gets 50,000.
        $fund = $this->savings('Garage fund', 150000);
        $project = Project::factory()->for($this->user)->create(['name' => 'Garage', 'currency' => 'COP', 'bucket_id' => $fund->id]);
        $this->item(['name' => 'X', 'price' => 100000, 'importance' => 4, 'project_id' => $project->id]);
        $this->item(['name' => 'Y', 'price' => 100000, 'importance' => 2, 'project_id' => $project->id]);

        $entry = $this->entry('Garage');

        $this->assertSame(['Garage fund', 200000.0, 150000.0, 75.0], [$entry['bucket']['name'], $entry['totals'][0]['wanted'], $entry['totals'][0]['funded'], $entry['totals'][0]['fundedPercent']]);
    }

    public function test_spent_is_what_the_purchases_really_cost_and_the_budget_left_follows(): void
    {
        // Budget 1,000,000. Bought: a 300,000 item that really cost 280,000, and a 50,000 item whose expense is gone
        // (its price counts): spent 330,000. Still wanted: 400,000. Left: 1,000,000 - 330,000 - 400,000 = 270,000.
        $project = Project::factory()->for($this->user)->create(['name' => 'Studio', 'currency' => 'COP', 'budget_amount' => 1000000]);
        $expense = Transaction::factory()->for($this->user)->create(['account_id' => $this->account->id, 'type' => 'expense', 'amount' => 280000]);
        $this->item(['name' => 'Desk', 'price' => 300000, 'project_id' => $project->id, 'status' => WishlistStatus::Purchased, 'purchased_transaction_id' => $expense->id]);
        $this->item(['name' => 'Lamp', 'price' => 50000, 'project_id' => $project->id, 'status' => WishlistStatus::Purchased]);
        $this->item(['name' => 'Chair', 'price' => 400000, 'project_id' => $project->id]);

        $totals = $this->entry('Studio')['totals'][0];

        $this->assertSame([400000.0, 330000.0, 270000.0], [$totals['wanted'], $totals['purchased'], $totals['budgetLeft']]);
    }

    public function test_the_monthly_pace_counts_the_months_until_the_target_date(): void
    {
        // Today is 2026-10-12 and the target 2027-01-12: three months (Nov 12, Dec 12, Jan 12). 300,000 is planned and
        // nothing is funded, so 100,000 a month. A target that has passed, or nothing missing, gives none.
        $soon = Project::factory()->for($this->user)->create(['name' => 'Soon', 'currency' => 'COP', 'target_date' => '2027-01-12']);
        $this->item(['name' => 'Bike', 'price' => 300000, 'project_id' => $soon->id]);
        $late = Project::factory()->for($this->user)->create(['name' => 'Late', 'currency' => 'COP', 'target_date' => '2026-09-01']);
        $this->item(['name' => 'Boat', 'price' => 300000, 'project_id' => $late->id]);
        $covered = Project::factory()->for($this->user)->create(['name' => 'Covered', 'currency' => 'COP', 'target_date' => '2027-01-12']);
        $this->item(['name' => 'Hat', 'price' => 30000, 'project_id' => $covered->id, 'bucket_id' => $this->savings('Hat fund', 30000)->id]);

        $this->assertSame(100000.0, $this->entry('Soon')['totals'][0]['neededPerMonth']);
        $this->assertNull($this->entry('Late')['totals'][0]['neededPerMonth']);
        $this->assertNull($this->entry('Covered')['totals'][0]['neededPerMonth']);
    }

    public function test_projects_compete_for_the_same_free_money(): void
    {
        // One income of 1,000,000 on 2026-11-05 and nothing else (the account starts empty). Each project wants an
        // 800,000 item. Planned together, the more wanted one is bought on the 5th and leaves 200,000: the other can
        // never be bought. (Planned alone, each of them said 2026-11-05.)
        $this->user->accounts()->update(['initial_balance' => 0]);
        PlannedEvent::factory()->for($this->user)->create(['type' => 'income', 'amount' => 1000000, 'currency' => 'COP', 'date' => '2026-11-05', 'certainty' => Certainty::Confirmed]);
        $first = Project::factory()->for($this->user)->create(['name' => 'First', 'currency' => 'COP']);
        $second = Project::factory()->for($this->user)->create(['name' => 'Second', 'currency' => 'COP']);
        $this->item(['name' => 'Wanted most', 'price' => 800000, 'importance' => 5, 'project_id' => $first->id]);
        $this->item(['name' => 'Wanted less', 'price' => 800000, 'importance' => 3, 'project_id' => $second->id]);

        $this->assertSame('2026-11-05', $this->entry('First')['totals'][0]['completion']);
        $this->assertNull($this->entry('Second')['totals'][0]['completion']);
    }

    public function test_a_project_without_items_is_listed_with_its_currency_and_zeros(): void
    {
        Project::factory()->for($this->user)->create(['name' => 'Empty', 'currency' => 'COP', 'budget_amount' => 500000]);

        $entry = $this->entry('Empty');

        $this->assertSame(500000.0, $entry['budget']);
        $this->assertSame(['COP', 0.0, 0.0, 500000.0], [$entry['totals'][0]['currency'], $entry['totals'][0]['wanted'], $entry['totals'][0]['purchased'], $entry['totals'][0]['budgetLeft']]);
    }

    public function test_the_target_date_is_met_when_the_last_item_is_bought_by_then(): void
    {
        // The account holds 1,000,000: a 300,000 item is bought today (2026-10-12), before either target date.
        $early = Project::factory()->for($this->user)->create(['name' => 'Early', 'currency' => 'COP', 'target_date' => '2026-12-31']);
        $this->item(['name' => 'Shoes', 'price' => 300000, 'project_id' => $early->id]);

        $totals = $this->entry('Early')['totals'][0];

        $this->assertSame(['2026-10-12', true], [$totals['completion'], $totals['onTime']]);
    }
}
