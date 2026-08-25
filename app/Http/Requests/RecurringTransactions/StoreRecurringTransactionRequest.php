<?php

namespace App\Http\Requests\RecurringTransactions;

use App\Models\RecurringTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('user_id', $this->user()->id)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)->orWhereNull('user_id'))],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', 'in:weekly,biweekly,monthly,yearly'],
            'next_due_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:next_due_date'],
        ];
    }
}
