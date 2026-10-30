<?php

namespace App\Modules\Ledger\Http\Requests\Transactions;

use App\Modules\Ledger\Enums\PaymentMethod;
use App\Modules\Ledger\Models\Account;
use App\Modules\Shared\Validation\LedgerRules;
use App\Modules\Shared\Validation\MoneyRules;
use Closure;
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

        $transaction = $this->route('transaction');

        return [
            'account_id' => [
                'required',
                'integer',
                LedgerRules::account($this->user()->id, activeOnly: true, keepId: $transaction->account_id),
                function (string $attribute, mixed $value, Closure $fail) use ($transaction) {
                    $currency = $transaction->account->currency;

                    if (Account::whereKey((int) $value)->value('currency') !== $currency) {
                        $fail(__('transactions.account_currency_mismatch', ['currency' => $currency]));
                    }
                },
            ],
            'category_id' => ['required', 'integer', LedgerRules::category($this->user()->id, $this->route('transaction')->type->value)],
            'amount' => ['required', ...MoneyRules::positive()],
            'date' => ['required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
        ];
    }
}
