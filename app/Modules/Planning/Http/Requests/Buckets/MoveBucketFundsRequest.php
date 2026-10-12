<?php

namespace App\Modules\Planning\Http\Requests\Buckets;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveBucketFundsRequest extends FormRequest
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
        $bucket = $this->route('bucket');

        return [
            'to_bucket_id' => [
                'required',
                'integer',
                Rule::exists('buckets', 'id')->where('user_id', $this->user()->id)->where('currency', $bucket->currency),
                Rule::notIn([$bucket->id]),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
