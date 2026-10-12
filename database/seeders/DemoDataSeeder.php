<?php

namespace Database\Seeders;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Category;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Planning\Enums\Certainty;
use App\Modules\Planning\Enums\RecurringFrequency;
use App\Modules\Planning\Services\PlannedEventService;
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

        if (Account::forUser($user)->exists()) {
            return;
        }

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
}
