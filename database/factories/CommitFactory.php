<?php

namespace Database\Factories;

use App\Domain\Git\Models\Commit;
use App\Domain\Git\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commit>
 */
#[UseModel(Commit::class)]
class CommitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repository_id' => Repository::factory(),
            'workspace_id' => function (array $attributes): string {
                return Repository::query()->findOrFail($attributes['repository_id'])->workspace_id;
            },
            'sha' => fake()->unique()->regexify('[a-f0-9]{40}'),
            'author_name' => fake()->name(),
            'author_email' => fake()->safeEmail(),
            'committed_at' => now()->subDays(fake()->numberBetween(1, 30)),
            'message' => fake()->sentence(),
            'branch' => 'main',
            'imported_at' => now(),
        ];
    }
}
