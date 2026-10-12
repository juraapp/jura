<?php

namespace App\Modules\Identity\Http\Requests\Preferences;

use App\Modules\Shared\Support\UserFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'date_format' => ['required', Rule::in(UserFormat::DATE_FORMATS)],
            'time_format' => ['required', Rule::in(UserFormat::TIME_FORMATS)],
            'decimal_separator' => ['nullable', Rule::in(UserFormat::DECIMAL_SEPARATORS), 'different:thousands_separator'],
            'thousands_separator' => ['nullable', Rule::in(UserFormat::THOUSANDS_SEPARATORS)],
            'fraction_digits' => ['nullable', 'integer', 'between:0,2'],
            'currency_display' => ['required', Rule::in(UserFormat::CURRENCY_DISPLAYS)],
            'currency_position' => ['required', Rule::in(UserFormat::CURRENCY_POSITIONS)],
        ];
    }
}
