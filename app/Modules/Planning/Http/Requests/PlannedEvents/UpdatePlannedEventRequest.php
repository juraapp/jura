<?php

namespace App\Modules\Planning\Http\Requests\PlannedEvents;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlannedEventRequest extends FormRequest
{
    use PlannedRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('plannedEvent'));
    }

    public function rules(): array
    {
        return [
            ...$this->plannedRules(),
            'date' => ['required', 'date'],
            'scope' => ['nullable', 'in:this,future'],
        ];
    }

    public function messages(): array
    {
        return $this->plannedMessages();
    }
}
