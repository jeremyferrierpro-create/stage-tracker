<?php

namespace Database\Factories;

use App\Domain\Identity\Models\Workspace;
use App\Domain\Organization\Models\Organization;
use App\Domain\Training\Models\TrainingProgram;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingProgram>
 */
#[UseModel(TrainingProgram::class)]
class TrainingProgramFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'organization_id' => fn (array $attributes): string => Organization::factory()->create([
                'workspace_id' => $attributes['workspace_id'],
            ])->id,
            'name' => 'Formation '.fake()->word(),
            'certification' => fake()->optional()->word(),
            'start_date' => now()->startOfDay()->subMonth(),
            'end_date' => now()->startOfDay()->addMonths(8),
        ];
    }
}
