<?php

namespace App\Modules\Ledger\Http\Requests\Transactions;

use App\Modules\Ledger\Enums\PaymentMethod;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Transaction;
use App\Modules\Shared\Validation\LedgerRules;
use App\Modules\Shared\Validation\MoneyRules;
use Closure;
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
                'from_account_id' => ['required', 'integer', 'different:to_account_id', LedgerRules::account($this->user()->id, activeOnly: true)],
                'to_account_id' => [
                    'required',
                    'integer',
                    LedgerRules::account($this->user()->id, activeOnly: true),
                    function (string $attribute, mixed $value, Closure $fail) {
                        $currencies = Account::whereKey([(int) $this->input('from_account_id'), (int) $value])->pluck('currency')->unique();

                        if ($currencies->count() > 1) {
                            $fail(__('transactions.currency_mismatch'));
                        }
                    },
                ],
                'amount' => ['required', ...MoneyRules::positive()],
                'date' => ['required', 'date'],
                'time' => ['nullable', 'date_format:H:i'],
                'description' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ];
        }

        return [
            'type' => ['required', 'in:income,expense'],
            'account_id' => ['required', 'integer', LedgerRules::account($this->user()->id, activeOnly: true)],
            'category_id' => ['required', 'integer', LedgerRules::category($this->user()->id, (string) $this->input('type'))],
            'amount' => ['required', ...MoneyRules::positive()],
            'date' => ['required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
        ];
    }
}
