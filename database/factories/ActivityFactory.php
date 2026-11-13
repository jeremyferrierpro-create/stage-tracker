<?php

namespace Database\Factories;

use App\Domain\Project\Models\Activity;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
#[UseModel(Activity::class)]
class ActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'workspace_id' => function (array $attributes): string {
                return Project::query()->findOrFail($attributes['project_id'])->workspace_id;
            },
            'title' => 'Activité '.fake()->word(),
            'description' => fake()->sentence(),
            'type' => 'feature',
            'status' => 'todo',
            'priority' => 'medium',
            'start_date' => now()->startOfDay()->subDays(3),
            'end_date' => now()->startOfDay()->addDays(7),
            'objectives' => fake()->sentence(),
            'results' => null,
            'difficulties' => null,
            'solutions' => null,
        ];
    }
}
