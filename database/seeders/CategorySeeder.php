<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * System categories (user_id null, visible to every user). Users can
     * create their own categories in addition to these.
     */
    public function run(): void
    {
        $expenseCategories = [
            ['name' => 'Food', 'icon' => 'banknotes', 'color' => '#f59e0b'],
            ['name' => 'Transportation', 'icon' => 'arrow-path', 'color' => '#3b82f6'],
            ['name' => 'Housing', 'icon' => 'home', 'color' => '#8b5cf6'],
            ['name' => 'Utilities', 'icon' => 'cog', 'color' => '#64748b'],
            ['name' => 'Entertainment', 'icon' => 'flag', 'color' => '#ec4899'],
            ['name' => 'Education', 'icon' => 'document-chart-bar', 'color' => '#06b6d4'],
            ['name' => 'Health', 'icon' => 'exclamation-triangle', 'color' => '#ef4444'],
            ['name' => 'Shopping', 'icon' => 'tag', 'color' => '#f97316'],
            ['name' => 'Technology', 'icon' => 'adjustments', 'color' => '#0ea5e9'],
            ['name' => 'Subscriptions', 'icon' => 'arrow-path', 'color' => '#a855f7'],
            ['name' => 'Travel', 'icon' => 'arrows-right-left', 'color' => '#14b8a6'],
            ['name' => 'Pets', 'icon' => 'flag', 'color' => '#84cc16'],
            ['name' => 'Debt', 'icon' => 'arrow-down-circle', 'color' => '#dc2626'],
            ['name' => 'Taxes', 'icon' => 'document-chart-bar', 'color' => '#78716c'],
            ['name' => 'Other', 'icon' => 'tag', 'color' => '#6b7280'],
        ];

        $incomeCategories = [
            ['name' => 'Salary', 'icon' => 'banknotes', 'color' => '#22c55e'],
            ['name' => 'Freelance', 'icon' => 'wallet', 'color' => '#10b981'],
            ['name' => 'Bonus', 'icon' => 'arrow-up-circle', 'color' => '#059669'],
            ['name' => 'Gift', 'icon' => 'flag', 'color' => '#84cc16'],
            ['name' => 'Investment Returns', 'icon' => 'arrow-trending-up', 'color' => '#16a34a'],
            ['name' => 'Sale', 'icon' => 'tag', 'color' => '#0d9488'],
            ['name' => 'Refund', 'icon' => 'arrow-path', 'color' => '#0891b2'],
            ['name' => 'Other', 'icon' => 'tag', 'color' => '#6b7280'],
        ];

        foreach ($expenseCategories as $category) {
            Category::withoutGlobalScopes()->updateOrCreate(
                ['name' => $category['name'], 'type' => CategoryType::Expense->value, 'user_id' => null],
                [...$category, 'type' => CategoryType::Expense->value, 'is_system' => true]
            );
        }

        foreach ($incomeCategories as $category) {
            Category::withoutGlobalScopes()->updateOrCreate(
                ['name' => $category['name'], 'type' => CategoryType::Income->value, 'user_id' => null],
                [...$category, 'type' => CategoryType::Income->value, 'is_system' => true]
            );
        }
    }
}
