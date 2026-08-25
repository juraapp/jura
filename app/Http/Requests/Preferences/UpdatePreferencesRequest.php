<?php

namespace App\Http\Requests\Preferences;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency_default' => ['required', 'string', 'size:3'],
            'locale' => ['required', 'in:es,en'],
            'timezone' => ['required', 'timezone'],
        ];
    }
}
