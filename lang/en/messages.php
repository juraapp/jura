<?php

return [
    'csv' => [
        'date' => 'Date',
        'type' => 'Type',
        'description' => 'Description',
        'category' => 'Category',
        'account' => 'Account',
        'currency' => 'Currency',
        'amount' => 'Amount',
        'notes' => 'Notes',
    ],

    'import' => [
        'could_not_read_file' => 'The file could not be read.',
        'empty_file' => 'The file is empty.',
        'too_many_rows' => 'The file has more than :max rows; split it into smaller files.',
        'missing_columns' => 'The file is missing required columns: :columns.',
        'invalid_type' => "Invalid type: ':value' (use 'Income' or 'Expense').",
        'account_not_found' => "Account ':account' not found.",
        'category_not_found' => "Category ':category' does not exist for type ':type'.",
        'invalid_amount' => "Invalid amount: ':value'.",
        'invalid_date' => "Invalid date: ':value'.",
        'description_too_long' => 'Description exceeds 255 characters.',
        'notes_too_long' => 'Notes exceed 2000 characters.',
        'template_account_name' => 'Your account name',
        'template_income_description' => 'August salary',
        'template_income_category' => 'Salary',
        'template_expense_description' => 'Monthly groceries',
        'template_expense_category' => 'Food',
        'template_expense_notes' => 'Supermarket purchase',
    ],
];
