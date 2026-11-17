<?php

namespace Database\Factories;

use App\Domain\Evaluation\Models\Competency;
use App\Domain\Identity\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Competency>
 */
#[UseModel(Competency::class)]
class CompetencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'code' => 'C'.fake()->unique()->numberBetween(1, 99),
            'title' => 'Compétence '.fake()->word(),
            'description' => null,
        ];
    }
}
