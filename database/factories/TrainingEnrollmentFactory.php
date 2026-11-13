<?php

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Training\Models\TrainingEnrollment;
use App\Domain\Training\Models\TrainingProgram;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingEnrollment>
 */
#[UseModel(TrainingEnrollment::class)]
class TrainingEnrollmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'training_program_id' => TrainingProgram::factory(),
            'workspace_id' => function (array $attributes): int {
                return TrainingProgram::query()->findOrFail($attributes['training_program_id'])->workspace_id;
            },
            'start_date' => now()->startOfDay()->subMonth(),
            'end_date' => now()->startOfDay()->addMonths(8),
            'status' => 'active',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'completed',
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'withdrawn',
        ]);
    }
}
