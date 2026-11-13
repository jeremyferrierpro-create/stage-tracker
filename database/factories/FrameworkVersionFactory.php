<?php

namespace Database\Factories;

use App\Domain\Framework\Models\CompetencyFramework;
use App\Domain\Framework\Models\FrameworkVersion;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FrameworkVersion>
 */
#[UseModel(FrameworkVersion::class)]
class FrameworkVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'framework_id' => CompetencyFramework::factory(),
            'version' => 'v'.fake()->numerify('1.0.#'),
            'effective_date' => now()->startOfDay(),
            'status' => 'draft',
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
