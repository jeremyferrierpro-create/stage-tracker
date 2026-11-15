<?php

namespace Database\Factories;

use App\Domain\Evidence\Models\Evidence;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evidence>
 */
#[UseModel(Evidence::class)]
class EvidenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'owner_id' => User::factory(),
            'title' => 'Preuve '.fake()->word(),
            'type' => 'screenshot',
            'description' => fake()->sentence(),
            'demonstrated' => fake()->sentence(),
            'storage_key' => 'evidences/test/file.png',
            'external_url' => null,
            'visibility' => 'private',
            'captured_at' => now(),
        ];
    }

    public function shared(): static
    {
        return $this->state(fn (array $attributes): array => [
            'visibility' => 'shared',
        ]);
    }

    public function external(): static
    {
        return $this->state(fn (array $attributes): array => [
            'storage_key' => null,
            'external_url' => 'https://example.test/preuve',
        ]);
    }
}
