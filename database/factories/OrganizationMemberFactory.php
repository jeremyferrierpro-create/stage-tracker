<?php

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\OrganizationMember;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationMember>
 */
#[UseModel(OrganizationMember::class)]
class OrganizationMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'workspace_id' => function (array $attributes): mixed {
                return Organization::query()->findOrFail($attributes['organization_id'])->workspace_id;
            },
            'user_id' => User::factory(),
            'role' => 'member',
        ];
    }
}
