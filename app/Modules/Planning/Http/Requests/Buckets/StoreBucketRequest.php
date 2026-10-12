<?php

namespace App\Modules\Planning\Http\Requests\Buckets;

use App\Modules\Planning\Models\Bucket;
use Illuminate\Foundation\Http\FormRequest;

class StoreBucketRequest extends FormRequest
{
    use BucketRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', Bucket::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->bucketRules();
    }
}
