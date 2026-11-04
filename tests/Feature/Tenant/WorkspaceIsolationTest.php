<?php

namespace Tests\Feature\Tenant;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceWithMember(User $user, string $role = 'owner'): Workspace
    {
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $workspace;
    }

    public function test_member_can_access_his_workspace_dashboard(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceWithMember($user);

        $this->actingAs($user)
            ->get(route('workspace.dashboard', $workspace))
            ->assertOk()
            ->assertSee($workspace->name);
    }

    public function test_member_of_another_workspace_is_forbidden(): void
    {
        $alice = User::factory()->create();
        $workspaceA = $this->workspaceWithMember($alice);

        $bob = User::factory()->create();
        $workspaceB = $this->workspaceWithMember($bob);

        $this->actingAs($alice)
            ->get(route('workspace.dashboard', $workspaceB))
            ->assertForbidden()
            ->assertDontSee($workspaceB->name);

        // Le workspace légitime reste accessible.
        $this->actingAs($alice)
            ->get(route('workspace.dashboard', $workspaceA))
            ->assertOk();
    }

    public function test_non_member_is_forbidden(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $this->actingAs($user)
            ->get(route('workspace.dashboard', $workspace))
            ->assertForbidden();
    }

    public function test_suspended_member_is_forbidden(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        WorkspaceMember::factory()->suspended()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'trainee',
        ]);

        $this->actingAs($user)
            ->get(route('workspace.dashboard', $workspace))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $workspace = Workspace::factory()->create();

        $this->get(route('workspace.dashboard', $workspace))
            ->assertRedirect(route('login'));
    }

    public function test_unknown_workspace_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/w/'.Str::uuid().'/dashboard')
            ->assertNotFound();
    }

    public function test_malformed_workspace_id_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/w/not-a-uuid/dashboard')
            ->assertNotFound();
    }
}
