<?php

namespace App\Modules\Ledger\Http\Requests\Transactions;

use App\Modules\Ledger\Enums\PaymentMethod;
use App\Modules\Shared\Validation\LedgerRules;
use App\Modules\Shared\Validation\MoneyRules;
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
                'amount' => ['required', ...MoneyRules::positive()],
                'date' => ['required', 'date'],
                'time' => ['nullable', 'date_format:H:i'],
                'description' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ];
        }

        return [
            'account_id' => ['required', 'integer', LedgerRules::account($this->user()->id, activeOnly: true, keepId: $this->route('transaction')->account_id)],
            'category_id' => ['required', 'integer', LedgerRules::category($this->user()->id)],
            'amount' => ['required', ...MoneyRules::positive()],
            'date' => ['required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
        ];
    }
}
