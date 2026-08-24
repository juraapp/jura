<?php

namespace App\Http\Requests\Transactions;

use App\Enums\PaymentMethod;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Transaction::class);
    }

    public function rules(): array
    {
        if ($this->input('type') === 'transfer') {
            return [
                'type' => ['required', 'in:transfer'],
                'from_account_id' => ['required', 'integer', 'different:to_account_id', 'exists:accounts,id'],
                'to_account_id' => ['required', 'integer', 'exists:accounts,id'],
                'amount' => ['required', 'numeric', 'gt:0'],
                'date' => ['required', 'date'],
                'description' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ];
        }

        return [
            'type' => ['required', 'in:income,expense'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
        ];
    }
}
