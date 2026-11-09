<?php

namespace App\Modules\Planning\Http\Requests\Projects;

use App\Modules\Planning\Enums\ProjectKind;
use App\Modules\Planning\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A project made on the spot from an item's form: just a name (and the item's currency).
 */
class QuickStoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'currency' => ['required', 'string', 'size:3'],
            'kind' => ['nullable', Rule::enum(ProjectKind::class)],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
