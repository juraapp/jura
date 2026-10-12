<?php

namespace App\Modules\Ledger\Http\Requests\Accounts;

use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Enums\YieldCompounding;
use App\Modules\Ledger\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Account::class);
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
            'yield_rate' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'yield_compounding' => ['nullable', Rule::enum(YieldCompounding::class)],
            'color' => ['required', 'string', 'max:7'],
            'masked_number' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
