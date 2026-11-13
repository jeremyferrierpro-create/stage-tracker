<?php

namespace Database\Factories;

use App\Domain\Internship\Models\Internship;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
#[UseModel(Project::class)]
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'internship_id' => Internship::factory(),
            'workspace_id' => function (array $attributes): string {
                return Internship::query()->findOrFail($attributes['internship_id'])->workspace_id;
            },
            'name' => 'Projet '.fake()->word(),
            'description' => fake()->sentence(),
            'status' => 'active',
            'start_date' => now()->startOfDay()->subWeek(),
            'end_date' => now()->startOfDay()->addMonths(2),
        ];
    }
}
