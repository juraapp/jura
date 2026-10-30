<?php

namespace App\Modules\Planning\Http\Requests\PlannedEvents;

use App\Modules\Ledger\Contracts\LedgerQuery;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkPlannedEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('plannedEvent'));
    }

    /**
     * The transaction must be the user's own, of the same type, and not already linked to another plan.
     */
    public function rules(): array
    {
        $event = $this->route('plannedEvent');

        return [
            'transaction_id' => [
                'required',
                'integer',
                Rule::exists('transactions', 'id')
                    ->where('user_id', $this->user()->id)
                    ->where('type', $event->type->value)
                    ->whereNull('deleted_at'),
                Rule::unique('planned_events', 'realized_transaction_id'),
                fn (string $attribute, mixed $value, Closure $fail) => app(LedgerQuery::class)->transactionCurrency($this->user()->id, (int) $value) !== $event->currency
                    ? $fail(__('planned-events.currency_mismatch', ['currency' => $event->currency]))
                    : null,
            ],
        ];
    }
}
