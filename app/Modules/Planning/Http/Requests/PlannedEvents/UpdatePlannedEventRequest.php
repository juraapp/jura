<?php

namespace App\Modules\Planning\Http\Requests\PlannedEvents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlannedEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('plannedEvent'));
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'type' => ['required', 'in:income,expense'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('user_id', $userId)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $userId)->orWhereNull('user_id'))],
            'certainty' => ['required', 'in:confirmed,expected,speculative'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allocation_overrides' => ['nullable', 'array'],
            'allocation_overrides.*.bucket_id' => ['required', 'integer', Rule::exists('buckets', 'id')->where('user_id', $userId)],
            'allocation_overrides.*.amount' => ['required', 'numeric', 'gt:0'],
            'scope' => ['nullable', 'in:this,future'],
        ];
    }
}
