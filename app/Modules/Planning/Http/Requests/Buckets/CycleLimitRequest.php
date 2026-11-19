<?php

namespace App\Modules\Planning\Http\Requests\Buckets;

use App\Modules\Shared\Validation\MoneyRules;
use Illuminate\Foundation\Http\FormRequest;

class CycleLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('bucket'));
    }

    /**
     * An empty amount removes the override.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['cap_amount' => ['nullable', ...MoneyRules::positive()]];
    }
}
