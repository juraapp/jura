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
                'from_account_id' => ['required', 'integer', 'different:to_account_id', Rule::exists('accounts', 'id')->where('user_id', $this->user()->id)],
                'to_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('user_id', $this->user()->id)],
                'amount' => ['required', 'numeric', 'gt:0'],
                'date' => ['required', 'date'],
                'description' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ];
        }

        return [
            'type' => ['required', 'in:income,expense'],
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('user_id', $this->user()->id)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)->orWhereNull('user_id'))],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
        ];
    }
}
