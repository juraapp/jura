<?php

namespace App\Modules\Planning\Http\Requests\Buckets;

use Illuminate\Foundation\Http\FormRequest;

class AdjustBucketFundsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('bucket'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'not_in:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
