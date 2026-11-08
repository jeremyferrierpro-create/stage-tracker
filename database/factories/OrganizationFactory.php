<?php

namespace Database\Factories;

use App\Domain\Identity\Models\Workspace;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
#[UseModel(Organization::class)]
class OrganizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->company(),
            'type' => 'company',
            'contact_email' => fake()->unique()->safeEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'archived_at' => null,
        ];
    }

    public function trainingCenter(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'training_center',
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'archived_at' => now(),
        ]);
    }
}
