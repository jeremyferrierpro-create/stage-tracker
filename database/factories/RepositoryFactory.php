<?php

namespace Database\Factories;

use App\Domain\Git\Models\Repository;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Repository>
 */
#[UseModel(Repository::class)]
class RepositoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'workspace_id' => function (array $attributes): string {
                return Project::query()->findOrFail($attributes['project_id'])->workspace_id;
            },
            'provider' => 'github',
            'external_id' => 'demo/repo-'.fake()->unique()->numberBetween(1, 99999),
            'name' => 'Dépôt '.fake()->word(),
            'repository_url' => 'https://github.com/demo/repo',
            'default_branch' => 'main',
            'token_encrypted' => null,
        ];
    }
}
