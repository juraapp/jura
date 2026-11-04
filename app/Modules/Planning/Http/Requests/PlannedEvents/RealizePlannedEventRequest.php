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

    /**
     * One-click confirm: whatever is not sent comes from the plan, and the date is the planned date (today
     * when the plan is dated later, since a real transaction cannot be in the future).
     */
    protected function prepareForValidation(): void
    {
        $event = $this->route('plannedEvent');
        $today = $this->user()->today();

        $this->merge(array_filter([
            'account_id' => $this->filled('account_id') ? null : $event->account_id,
            'category_id' => $this->filled('category_id') ? null : $event->category_id,
            'amount' => $this->filled('amount') ? null : $event->amount->toDecimalString(),
            'date' => $this->filled('date') ? null : ($event->date->gt($today) ? $today : $event->date)->toDateString(),
        ], fn (mixed $value) => $value !== null));
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
            'date' => ['required', 'date', 'before_or_equal:'.$this->user()->today()->toDateString()],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['date.before_or_equal' => __('planned-events.future_not_allowed')];
    }
}
