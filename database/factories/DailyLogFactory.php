<?php

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Internship\Models\Internship;
use App\Domain\Journal\Models\DailyLog;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyLog>
 */
#[UseModel(DailyLog::class)]
class DailyLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'internship_id' => Internship::factory(),
            'workspace_id' => function (array $attributes): string {
                return Internship::query()->findOrFail($attributes['internship_id'])->workspace_id;
            },
            'author_id' => User::factory(),
            'work_date' => now()->startOfDay(),
            'status' => 'draft',
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }
}
