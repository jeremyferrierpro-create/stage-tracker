<?php

namespace Database\Factories;

use App\Domain\Collaboration\Models\Review;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Project\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
#[UseModel(Review::class)]
class ReviewFactory extends Factory
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
            'reviewer_id' => function (array $attributes): int {
                $activity = Activity::query()->findOrFail($attributes['activity_id']);

                return (int) Workspace::query()->whereKey($activity->workspace_id)->value('owner_id');
            },
            'status' => 'requested',
            'feedback' => null,
            'created_at' => now(),
        ];
    }
}
