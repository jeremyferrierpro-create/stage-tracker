<?php

namespace Database\Factories;

use App\Domain\Framework\Models\CompetencyFramework;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetencyFramework>
 */
#[UseModel(CompetencyFramework::class)]
class CompetencyFrameworkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Référentiel '.fake()->unique()->word(),
            'issuing_authority' => fake()->company(),
            'description' => fake()->sentence(),
            'status' => 'draft',
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'active',
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'archived',
        ]);
    }
}
