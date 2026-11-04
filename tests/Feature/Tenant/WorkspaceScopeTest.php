<?php

namespace Tests\Feature\Tenant;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Identity\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ScopedWorkspaceMember;
use Tests\TestCase;

class WorkspaceScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_queries_are_scoped_to_the_current_workspace(): void
    {
        $workspaceA = Workspace::factory()->create();
        $workspaceB = Workspace::factory()->create();

        WorkspaceMember::factory()->count(2)->create(['workspace_id' => $workspaceA->id]);
        WorkspaceMember::factory()->create(['workspace_id' => $workspaceB->id]);

        $context = app(WorkspaceContext::class);

        $context->set($workspaceA);
        $this->assertSame(2, ScopedWorkspaceMember::query()->count());

        $context->set($workspaceB);
        $this->assertSame(1, ScopedWorkspaceMember::query()->count());

        $context->clear();
        $this->assertSame(3, ScopedWorkspaceMember::query()->count());
    }

    public function test_workspace_id_is_filled_on_create(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();

        $context = app(WorkspaceContext::class);
        $context->set($workspace);

        $member = ScopedWorkspaceMember::create([
            'user_id' => $user->id,
            'role' => 'trainee',
            'status' => 'active',
        ]);

        $this->assertSame($workspace->id, $member->workspace_id);
    }
}
