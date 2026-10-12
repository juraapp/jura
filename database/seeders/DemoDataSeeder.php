<?php

namespace Database\Seeders;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Enums\Certainty;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Enums\ProjectKind;
use App\Modules\Planning\Enums\RecurringFrequency;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\Project;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Planning\Services\BucketLedger;
use App\Modules\Planning\Services\PlannedEventService;
use App\Modules\Shared\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Realistic sample data for exploring the app by hand:
 * `vendor/bin/sail artisan db:seed --class=DemoDataSeeder`. Safe to re-run.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            User::factory()->make(['name' => 'Test User'])->getAttributes(),
        );

        if (! Account::forUser($user)->exists()) {
            $this->seedLedgerAndPlans($user);
        }

        if (! Bucket::forUser($user)->exists()) {
            $this->seedBuckets($user);
        }

        if (! Project::forUser($user)->exists()) {
            $this->seedWishlist($user);
        }
    }

    private function seedLedgerAndPlans(User $user): void
    {
        $cash = Account::factory()->for($user)->create(['name' => 'Physical Money', 'type' => AccountType::Cash, 'initial_balance' => 250000, 'color' => '#16a34a', 'icon' => 'banknotes']);
        $debit = Account::factory()->for($user)->create(['name' => 'Debit card', 'type' => AccountType::Bank, 'initial_balance' => 1800000, 'color' => '#2563eb', 'icon' => 'wallet']);

        $category = fn (string $name) => Category::withoutGlobalScopes()->whereNull('user_id')->where('name', $name)->firstOrFail();
        $spend = [
            [$debit, 'Housing', 1200000, -20, 'Rent'],
            [$debit, 'Food', 180000, -14, 'Groceries'],
            [$cash, 'Transportation', 45000, -9, 'Transit'],
            [$debit, 'Utilities', 210000, -6, 'Utilities'],
            [$cash, 'Food', 62000, -3, 'Street food'],
        ];
        foreach ($spend as [$account, $categoryName, $amount, $days, $description]) {
            Transaction::factory()->for($user)->for($account)->create([
                'type' => 'expense', 'category_id' => $category($categoryName)->id, 'amount' => $amount,
                'date' => Carbon::today()->addDays($days), 'description' => $description,
            ]);
        }

        $incomeCategory = Category::withoutGlobalScopes()->whereNull('user_id')->where('type', 'income')->orderBy('id')->firstOrFail();
        Transaction::factory()->for($user)->for($debit)->income()->create([
            'category_id' => $incomeCategory->id, 'amount' => 3200000, 'date' => Carbon::today()->subDays(18), 'description' => 'Salary',
        ]);

        $planned = app(PlannedEventService::class);
        $base = ['currency' => 'COP', 'account_id' => $debit->id];

        $planned->createSeries($user, $base + [
            'type' => 'income', 'description' => 'Salary', 'amount' => 3200000, 'certainty' => Certainty::Confirmed->value,
            'date' => Carbon::today()->addDays(12)->toDateString(), 'frequency' => RecurringFrequency::Monthly->value,
        ]);
        $planned->createSeries($user, $base + [
            'type' => 'expense', 'description' => 'Rent', 'amount' => 1200000, 'certainty' => Certainty::Confirmed->value,
            'category_id' => $category('Housing')->id,
            'date' => Carbon::today()->addDays(10)->toDateString(), 'frequency' => RecurringFrequency::Monthly->value,
        ]);
        $planned->createEvent($user, $base + [
            'type' => 'income', 'description' => 'Freelance project (client A)', 'amount' => 1500000, 'certainty' => Certainty::Expected->value,
            'date' => Carbon::today()->addDays(25)->toDateString(),
        ]);
        $planned->createEvent($user, $base + [
            'type' => 'income', 'description' => 'Possible bonus', 'amount' => 2000000, 'certainty' => Certainty::Speculative->value,
            'date' => Carbon::today()->addMonthsNoOverflow(2)->toDateString(),
        ]);
        $planned->createEvent($user, $base + [
            'type' => 'expense', 'description' => 'Dentist', 'amount' => 350000, 'certainty' => Certainty::Confirmed->value,
            'category_id' => $category('Health')->id, 'date' => Carbon::today()->addDays(18)->toDateString(),
        ]);
    }

    private function seedBuckets(User $user): void
    {
        $cash = Account::forUser($user)->where('name', 'Physical Money')->first();
        $groceries = Category::withoutGlobalScopes()->whereNull('user_id')->where('name', 'Food')->firstOrFail();
        $funds = app(BucketLedger::class);

        $car = Bucket::factory()->savings()->for($user)->priority(10)->rule(FillRule::Percent, 20)->create([
            'name' => 'Car', 'currency' => 'COP', 'cap_amount' => 30000000,
        ]);
        $trip = Bucket::factory()->savings()->for($user)->priority(20)->rule(FillRule::Fixed, 150000)->create([
            'name' => 'Trip to the coast', 'currency' => 'COP', 'cap_amount' => 4000000,
            'allowed_account_ids' => $cash ? [$cash->id] : null,
        ]);
        Bucket::factory()->for($user)->priority(30)->create([
            'name' => 'Groceries', 'category_id' => $groceries->id, 'currency' => 'COP', 'cap_amount' => 600000, 'cycle' => 'monthly',
        ]);
        $default = $funds->ensureDefaultBucket($user, 'COP');

        $funds->credit($car, Money::of(2400000), AllocationSource::Adjustment, null, Carbon::today()->subDays(30), 'Starting balance');
        $funds->credit($trip, Money::of(600000), AllocationSource::Adjustment, null, Carbon::today()->subDays(30), 'Starting balance');
        $funds->credit($default, Money::of(310000), AllocationSource::Adjustment, null, Carbon::today()->subDays(30), 'Starting balance');
    }

    private function seedWishlist(User $user): void
    {
        $cash = Account::forUser($user)->where('name', 'Physical Money')->first();
        $funds = app(BucketLedger::class);

        $hobbyFund = Bucket::factory()->savings()->for($user)->priority(25)->rule(FillRule::Percent, 5)->create([
            'name' => 'Hobby fund', 'currency' => 'COP', 'cap_amount' => 3000000,
        ]);
        $funds->credit($hobbyFund, Money::of(900000), AllocationSource::Adjustment, null, Carbon::today()->subDays(20), 'Starting balance');

        $cycling = Project::factory()->for($user)->create(['name' => 'Cycling', 'kind' => ProjectKind::Hobby, 'color' => '#16a34a']);
        $studio = Project::factory()->for($user)->create(['name' => 'Home studio', 'kind' => ProjectKind::Project, 'color' => '#7c3aed']);

        $items = [
            [$cycling, 'Road bike', 2500000, 5, $hobbyFund, null, Carbon::today()->addMonths(5)],
            [$cycling, 'Helmet and lights', 380000, 3, $hobbyFund, null, null],
            [$studio, 'Studio monitor pair', 1800000, 4, null, null, Carbon::today()->addMonths(3)],
            [$studio, 'Standing desk', 1200000, 2, null, $cash ? [$cash->id] : null, null],
            [null, 'Camera lens', 2200000, 3, null, null, null],
        ];

        foreach ($items as [$project, $name, $price, $importance, $bucket, $accounts, $desiredBy]) {
            WishlistItem::factory()->for($user)->create([
                'project_id' => $project?->id, 'bucket_id' => $bucket?->id, 'name' => $name, 'price' => $price,
                'importance' => $importance, 'allowed_account_ids' => $accounts, 'desired_by' => $desiredBy, 'currency' => 'COP',
            ]);
        }
    }
}
