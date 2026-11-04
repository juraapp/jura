<?php

namespace App\Modules\Planning\Http\Requests\PlannedEvents;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Edits a whole repeating plan. Its kind never changes; the next date is today or later.
 */
class UpdatePlannedSeriesRequest extends FormRequest
{
    use PlannedRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('plannedEventSeries'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['type' => $this->route('plannedEventSeries')->type->value]);
    }

    public function rules(): array
    {
        return [
            ...$this->plannedRules(),
            'frequency' => ['required', 'in:weekly,biweekly,monthly,yearly'],
            'next_date' => ['required', 'date', 'after_or_equal:'.$this->user()->today()->toDateString()],
            'ends_on' => ['nullable', 'date', 'after:next_date'],
        ];
    }

    public function messages(): array
    {
        return $this->plannedMessages();
    }
}
