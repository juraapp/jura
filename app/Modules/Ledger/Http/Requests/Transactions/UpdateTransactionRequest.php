<?php

namespace App\Modules\Ledger\Http\Requests\Transactions;

use App\Modules\Ledger\Enums\PaymentMethod;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Shared\Validation\LedgerRules;
use App\Modules\Shared\Validation\MoneyRules;
use App\Modules\Shared\Validation\PlanningRules;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTransactionRequest extends FormRequest
{
    use GuardsFutureDates;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('transaction'));
    }

    /**
     * The bucket of a wish-list purchase's expense is fixed by the purchase.
     *
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $transaction = $this->route('transaction');

                if ($this->has('bucket_id')
                    && (int) $this->input('bucket_id') !== (int) $transaction->bucket_id
                    && PlanningRules::isWishlistPurchase($this->user()->id, $transaction->id)) {
                    $validator->errors()->add('bucket_id', __('transactions.bucket_locked_by_purchase'));
                }
            },
        ];
    }

    public function rules(): array
    {
        if ($this->route('transaction')->type->isTransfer()) {
            return [
                'amount' => ['required', ...MoneyRules::positive()],
                'date' => ['required', 'date', $this->futureDateGuard($this->route('transaction')->date->toDateString())],
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
            'category_id' => ['required', 'integer', LedgerRules::category($this->user()->id, $transaction->type->value, keepId: $transaction->category_id)],
            'amount' => ['required', ...MoneyRules::positive()],
            'date' => ['required', 'date', $this->futureDateGuard($this->route('transaction')->date->toDateString())],
            'time' => ['nullable', 'date_format:H:i'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'bucket_id' => [
                'nullable',
                Rule::prohibitedIf($transaction->type !== TransactionType::Expense),
                'integer',
                PlanningRules::spendableBucket($this->user()->id, $transaction->account->currency, $transaction->bucket_id),
                function (string $attribute, mixed $value, Closure $fail) {
                    if (Account::whereKey((int) $this->input('account_id'))->value('on_budget') === false) {
                        $fail(__('transactions.bucket_off_budget'));
                    }
                },
            ],
        ];
    }
}
