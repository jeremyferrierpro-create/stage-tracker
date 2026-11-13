<?php

namespace Database\Factories;

use App\Domain\Project\Models\Activity;
use App\Domain\Project\Models\Task;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
#[UseModel(Task::class)]
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'workspace_id' => function (array $attributes): string {
                return Activity::query()->findOrFail($attributes['activity_id'])->workspace_id;
            },
            'title' => 'Tâche '.fake()->word(),
            'description' => fake()->sentence(),
            'priority' => 'medium',
            'status' => 'todo',
            'position' => 0,
        ];
    }
}
