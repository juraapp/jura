<?php

namespace App\Modules\Planning\Http\Requests\PlannedEvents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RealizePlannedEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('plannedEvent'));
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('user_id', $userId)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $userId)->orWhereNull('user_id'))],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
