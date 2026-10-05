<?php

namespace App\Http\Requests\Accounts;

use App\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('account'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'institution' => ['nullable', 'string', 'max:255'],
            'initial_balance' => ['required', 'numeric'],
            'currency' => ['required', 'string', 'size:3'],
            'color' => ['required', 'string', 'max:7'],
            'masked_number' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
