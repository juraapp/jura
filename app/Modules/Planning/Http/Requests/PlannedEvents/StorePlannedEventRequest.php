<?php

namespace App\Modules\Planning\Http\Requests\PlannedEvents;

use App\Modules\Planning\Models\PlannedEvent;
use Illuminate\Foundation\Http\FormRequest;

class StorePlannedEventRequest extends FormRequest
{
    use PlannedRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', PlannedEvent::class);
    }

    public function rules(): array
    {
        return [
            ...$this->plannedRules(),
            'date' => ['required', 'date'],
            'frequency' => ['nullable', 'in:weekly,biweekly,monthly,yearly'],
            'ends_on' => ['nullable', 'date', 'after:date'],
        ];
    }

    public function messages(): array
    {
        return $this->plannedMessages();
    }
}
