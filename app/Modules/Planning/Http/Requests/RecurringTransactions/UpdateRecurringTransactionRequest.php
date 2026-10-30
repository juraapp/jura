<?php

namespace App\Modules\Planning\Http\Requests\RecurringTransactions;

use App\Modules\Shared\Validation\LedgerRules;
use App\Modules\Shared\Validation\MoneyRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRecurringTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('recurringTransaction'));
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:income,expense'],
            'account_id' => ['required', 'integer', LedgerRules::account($this->user()->id, activeOnly: true, keepId: $this->route('recurringTransaction')->account_id)],
            'category_id' => ['required', 'integer', LedgerRules::category($this->user()->id)],
            'amount' => ['required', ...MoneyRules::positive()],
            'description' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', 'in:weekly,biweekly,monthly,yearly'],
            'next_due_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:next_due_date'],
        ];
    }
}
