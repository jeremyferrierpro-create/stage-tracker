<?php

namespace Database\Factories;

use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
#[UseModel(Invitation::class)]
class InvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => 'trainee',
            'token_hash' => hash('sha256', Str::random(48)),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'created_by' => User::factory(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'accepted_at' => now(),
        ]);
    }

    public function role(string $role): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => $role,
        ]);
    }
}
