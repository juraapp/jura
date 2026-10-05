<?php

namespace App\Http\Requests\Transactions;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('transaction'));
    }

    public function rules(): array
    {
        if ($this->route('transaction')->type->isTransfer()) {
            return [
                'amount' => ['required', 'numeric', 'gt:0'],
                'date' => ['required', 'date'],
                'description' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ];
        }

        return [
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
