<?php

namespace Tests\Feature\Tenant;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class WorkspacePolicyTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceWithRole(User $user, string $role): Workspace
    {
        $owner = $role === 'owner' ? $user : User::factory()->create();

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
        ]);

        return $workspace;
    }

    public function test_owner_can_manage_everything(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceWithRole($user, 'owner');

        $this->assertTrue(Gate::forUser($user)->allows('view', $workspace));
        $this->assertTrue(Gate::forUser($user)->allows('update', $workspace));
        $this->assertTrue(Gate::forUser($user)->allows('manageMembers', $workspace));
        $this->assertTrue(Gate::forUser($user)->allows('delete', $workspace));
    }

    public function test_admin_can_manage_but_not_delete(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceWithRole($user, 'admin');

        $this->assertTrue(Gate::forUser($user)->allows('view', $workspace));
        $this->assertTrue(Gate::forUser($user)->allows('update', $workspace));
        $this->assertTrue(Gate::forUser($user)->allows('manageMembers', $workspace));
        $this->assertFalse(Gate::forUser($user)->allows('delete', $workspace));
    }

    public function test_trainee_can_only_view(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceWithRole($user, 'trainee');

        $this->assertTrue(Gate::forUser($user)->allows('view', $workspace));
        $this->assertFalse(Gate::forUser($user)->allows('update', $workspace));
        $this->assertFalse(Gate::forUser($user)->allows('manageMembers', $workspace));
        $this->assertFalse(Gate::forUser($user)->allows('delete', $workspace));
    }

    public function test_viewer_can_only_view(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceWithRole($user, 'viewer');

        $this->assertTrue(Gate::forUser($user)->allows('view', $workspace));
        $this->assertFalse(Gate::forUser($user)->allows('update', $workspace));
        $this->assertFalse(Gate::forUser($user)->allows('manageMembers', $workspace));
    }

    public function test_non_member_cannot_view(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $this->assertFalse(Gate::forUser($user)->allows('view', $workspace));
        $this->assertFalse(Gate::forUser($user)->allows('update', $workspace));
    }

    public function test_suspended_member_cannot_view(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        WorkspaceMember::factory()->suspended()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $this->assertFalse(Gate::forUser($user)->allows('view', $workspace));
        $this->assertFalse(Gate::forUser($user)->allows('update', $workspace));
    }
}
