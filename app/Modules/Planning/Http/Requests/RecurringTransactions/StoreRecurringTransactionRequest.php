<?php

namespace App\Modules\Planning\Http\Requests\RecurringTransactions;

use App\Modules\Planning\Models\RecurringTransaction;
use App\Modules\Shared\Validation\LedgerRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecurringTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', RecurringTransaction::class);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:income,expense'],
            'account_id' => ['required', 'integer', LedgerRules::account($this->user()->id, activeOnly: true)],
            'category_id' => ['required', 'integer', LedgerRules::category($this->user()->id)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', 'in:weekly,biweekly,monthly,yearly'],
            'next_due_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:next_due_date'],
        ];
    }
}
