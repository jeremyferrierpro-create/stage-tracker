<?php

namespace Tests\Feature\Internship;

use App\Domain\Framework\Models\FrameworkVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Organization\Models\Organization;
use App\Domain\Project\Models\Activity;
use App\Domain\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternshipTest extends TestCase
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

    public function test_owner_creates_an_internship(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);
        $trainee = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $trainee->id,
            'role' => 'trainee',
        ]);
        $organization = Organization::factory()->create(['workspace_id' => $workspace->id]);
        $version = FrameworkVersion::factory()->published()->create();

        $this->actingAs($owner)
            ->post(route('workspace.internships.store', $workspace), [
                'title' => 'Stage Démo',
                'trainee_id' => $trainee->id,
                'host_organization_id' => $organization->id,
                'framework_version_id' => $version->id,
                'start_date' => '2026-09-01',
                'end_date' => '2027-01-31',
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('internships', [
            'workspace_id' => $workspace->id,
            'trainee_id' => $trainee->id,
            'title' => 'Stage Démo',
        ]);
    }

    public function test_trainee_cannot_create_an_internship(): void
    {
        $trainee = User::factory()->create();
        $workspace = $this->workspaceWithMember($trainee, 'trainee');
        $organization = Organization::factory()->create(['workspace_id' => $workspace->id]);
        $version = FrameworkVersion::factory()->published()->create();

        $this->actingAs($trainee)
            ->post(route('workspace.internships.store', $workspace), [
                'title' => 'Interdit',
                'trainee_id' => $trainee->id,
                'host_organization_id' => $organization->id,
                'framework_version_id' => $version->id,
                'start_date' => '2026-09-01',
                'end_date' => '2027-01-31',
                'status' => 'draft',
            ])
            ->assertForbidden();

        $this->assertSame(0, Internship::count());
    }

    public function test_internship_is_isolated_between_workspaces(): void
    {
        $ownerA = User::factory()->create();
        $workspaceA = $this->workspaceWithMember($ownerA);
        $internshipA = Internship::factory()->create(['workspace_id' => $workspaceA->id]);

        $ownerB = User::factory()->create();
        $workspaceB = $this->workspaceWithMember($ownerB);

        $this->actingAs($ownerB)
            ->get(route('workspace.internships.show', [$workspaceB, $internshipA]))
            ->assertNotFound();
    }

    public function test_mt04_two_internships_of_same_trainee_stay_separated(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);
        $trainee = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $trainee->id,
            'role' => 'trainee',
        ]);

        $organization = Organization::factory()->create(['workspace_id' => $workspace->id]);
        $version = FrameworkVersion::factory()->published()->create();

        $internshipA = Internship::factory()->create([
            'workspace_id' => $workspace->id,
            'trainee_id' => $trainee->id,
            'host_organization_id' => $organization->id,
            'framework_version_id' => $version->id,
            'title' => 'Stage A',
        ]);
        $internshipB = Internship::factory()->create([
            'workspace_id' => $workspace->id,
            'trainee_id' => $trainee->id,
            'host_organization_id' => $organization->id,
            'framework_version_id' => $version->id,
            'title' => 'Stage B',
        ]);

        $projectA = Project::factory()->create([
            'internship_id' => $internshipA->id,
            'workspace_id' => $workspace->id,
            'name' => 'Projet A',
        ]);
        $projectB = Project::factory()->create([
            'internship_id' => $internshipB->id,
            'workspace_id' => $workspace->id,
            'name' => 'Projet B',
        ]);

        Activity::factory()->create([
            'project_id' => $projectA->id,
            'workspace_id' => $workspace->id,
            'title' => 'Activité propre à A',
        ]);
        Activity::factory()->create([
            'project_id' => $projectB->id,
            'workspace_id' => $workspace->id,
            'title' => 'Activité propre à B',
        ]);

        $this->actingAs($owner)
            ->get(route('workspace.internships.projects.activities.index', [$workspace, $internshipA, $projectA]))
            ->assertOk()
            ->assertSee('Activité propre à A')
            ->assertDontSee('Activité propre à B');

        $this->actingAs($owner)
            ->get(route('workspace.internships.projects.activities.index', [$workspace, $internshipB, $projectB]))
            ->assertOk()
            ->assertSee('Activité propre à B')
            ->assertDontSee('Activité propre à A');
    }
}
