<?php

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Internship\Models\Internship;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Internship>
 */
#[UseModel(Internship::class)]
class InternshipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'trainee_id' => User::factory(),
            'company_name' => 'Entreprise '.fake()->word(),
            'title' => 'Stage '.fake()->word(),
            'start_date' => now()->startOfDay()->subMonth(),
            'end_date' => now()->startOfDay()->addMonths(4),
            'objectives' => fake()->sentence(),
            'status' => 'draft',
        ];
    }
}
