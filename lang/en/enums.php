<?php

return [
    'account_type' => [
        'bank' => 'Bank account',
        'wallet' => 'Digital wallet',
        'cash' => 'Cash',
        'credit_card' => 'Credit card',
        'savings' => 'Savings account',
    ],

    'budget_period_type' => [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
    ],

    'category_type' => [
        'income' => 'Income',
        'expense' => 'Expense',
    ],

    'payment_method' => [
        'cash' => 'Cash',
        'debit_card' => 'Debit card',
        'credit_card' => 'Credit card',
        'bank_transfer' => 'Bank transfer',
        'other' => 'Other',
    ],

    'recurring_frequency' => [
        'weekly' => 'Weekly',
        'biweekly' => 'Biweekly',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
    ],

    'savings_goal_status' => [
        'active' => 'Active',
        'completed' => 'Completed',
        'archived' => 'Archived',
    ],

    'transaction_type' => [
        'income' => 'Income',
        'expense' => 'Expense',
        'transfer' => 'Transfer',
    ],

    'certainty' => [
        'confirmed' => 'Confirmed',
        'expected' => 'Expected',
        'speculative' => 'Speculative',
    ],

    'planned_event_status' => [
        'planned' => 'Planned',
        'realized' => 'Done',
        'skipped' => 'Skipped',
    ],

    'forecast_view' => [
        'conservative' => 'Conservative',
        'expected' => 'Expected',
        'optimistic' => 'Optimistic',
    ],

    'fill_rule' => [
        'fixed' => 'Fixed amount',
        'percent' => 'Percent of income',
        'fill_to_target' => 'Fill up to its target',
    ],

    'carry_over' => [
        'carry' => 'Keep it in this bucket',
        'release' => 'Return it to the default bucket',
        'overflow' => 'Move it to another bucket',
    ],

    'bucket_kind' => [
        'spending' => 'Spending limit',
        'savings' => 'Savings',
    ],

    'allocation_source' => [
        'income' => 'Income',
        'planned' => 'Planned income',
        'transfer' => 'Moved between buckets',
        'carry' => 'End of cycle',
        'spend' => 'Purchase',
        'adjustment' => 'Manual adjustment',
        'opening' => 'Opening balance',
    ],

    'project_kind' => [
        'project' => 'Project',
        'hobby' => 'Hobby',
        'category' => 'Category',
        'other' => 'Other',
    ],

    'wishlist_status' => [
        'wanted' => 'Wanted',
        'purchased' => 'Purchased',
        'dropped' => 'Dropped',
    ],
];
