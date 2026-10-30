<?php

namespace App\Modules\Planning\Http\Requests\Buckets;

use App\Modules\Shared\Validation\MoneyRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveBucketFundsRequest extends FormRequest
{
    public const READY = 'ready';

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('bucket'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $bucket = $this->route('bucket');

        return [
            'from_ready' => ['sometimes', 'boolean'],
            // Another bucket of the same currency, or "ready" to give the money back to ready to assign.
            'to_bucket_id' => [
                'exclude_if:from_ready,true,1',
                'required',
                ...($this->input('to_bucket_id') === self::READY ? [] : [
                    'integer',
                    Rule::exists('buckets', 'id')->where('user_id', $this->user()->id)->where('currency', $bucket->currency),
                    Rule::notIn([$bucket->id]),
                ]),
            ],
            'amount' => ['required', ...MoneyRules::positive()],
            'description' => ['nullable', 'string', 'max:255'],
            'confirm_over_assign' => ['sometimes', 'boolean'],
        ];
    }
}
