<?php

namespace App\Modules\Planning\Http\Requests\Buckets;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBucketRequest extends FormRequest
{
    use BucketRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('bucket'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->bucketRules($this->route('bucket'));
    }
}
