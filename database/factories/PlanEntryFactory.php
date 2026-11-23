<?php

namespace Database\Factories;

use App\Domain\Internship\Models\Internship;
use App\Domain\Planning\Models\PlanEntry;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanEntry>
 */
#[UseModel(PlanEntry::class)]
class PlanEntryFactory extends Factory
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
            'project_id' => null,
            'plan_date' => now()->toDateString(),
            'slot' => 'morning',
            'title' => fake()->sentence(4),
            'planned_minutes' => 105,
            'position' => 0,
        ];
    }
}
