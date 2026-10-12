<?php

namespace App\Modules\Shared\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The ids a bulk action applies to. Rows that are not the user's are simply not found
 * (every model is scoped to the signed-in user), never touched.
 */
class BulkIdsRequest extends FormRequest
{
    public const MAX_IDS = 500;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_IDS],
            'ids.*' => ['integer'],
        ];
    }
}
