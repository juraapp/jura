<?php

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Enums\ProjectKind;
use App\Modules\Planning\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'kind' => ProjectKind::Project,
            'color' => '#2563eb',
            'icon' => 'flag',
            'notes' => null,
        ];
    }
}
