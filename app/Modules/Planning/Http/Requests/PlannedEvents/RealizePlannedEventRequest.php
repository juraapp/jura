<?php

namespace App\Modules\Planning\Http\Requests\PlannedEvents;

use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Shared\Validation\LedgerRules;
use App\Modules\Shared\Validation\MoneyRules;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class RealizePlannedEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('plannedEvent'));
    }

    public function rules(): array
    {
        $userId = $this->user()->id;
        $currency = $this->route('plannedEvent')->currency;

        return [
            'account_id' => [
                'required',
                'integer',
                LedgerRules::account($userId, activeOnly: true),
                fn (string $attribute, mixed $value, Closure $fail) => app(LedgerQuery::class)->accountCurrency($userId, (int) $value) !== $currency
                    ? $fail(__('planned-events.currency_mismatch', ['currency' => $currency]))
                    : null,
            ],
            'category_id' => ['required', 'integer', LedgerRules::category($userId, $this->route('plannedEvent')->type->value)],
            'amount' => ['nullable', ...MoneyRules::positive()],
            'date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
