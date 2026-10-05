<?php

namespace App\Http\Requests\Categories;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Category::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CategoryType::class)],
            'icon' => ['required', 'string', 'max:32'],
            'color' => ['required', 'string', 'max:7'],
        ];
    }
}
