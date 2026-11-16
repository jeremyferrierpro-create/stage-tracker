<?php

namespace Database\Factories;

use App\Domain\Collaboration\Models\Comment;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Project\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
#[UseModel(Comment::class)]
class CommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'workspace_id' => function (array $attributes): string {
                return Activity::query()->findOrFail($attributes['activity_id'])->workspace_id;
            },
            'author_id' => function (array $attributes): int {
                $activity = Activity::query()->findOrFail($attributes['activity_id']);

                return (int) Workspace::query()->whereKey($activity->workspace_id)->value('owner_id');
            },
            'content' => fake()->sentence(),
            'visibility' => 'stage',
            'created_at' => now(),
        ];
    }
}
