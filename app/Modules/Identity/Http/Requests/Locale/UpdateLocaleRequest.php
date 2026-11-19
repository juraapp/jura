<?php

namespace App\Modules\Identity\Http\Requests\Locale;

use App\Modules\Shared\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', Rule::in(Locale::SUPPORTED)],
        ];
    }
}
