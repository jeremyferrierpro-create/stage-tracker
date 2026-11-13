<?php

namespace Tests\Feature\Training;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Organization\Models\Organization;
use App\Domain\Training\Models\TrainingProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingProgramTest extends TestCase
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

    public function test_owner_creates_a_training_program(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);
        $organization = Organization::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => 'training_center',
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.training.store', $workspace), [
                'organization_id' => $organization->id,
                'name' => 'Programme DWWM',
                'certification' => 'DWWM',
                'start_date' => '2026-09-01',
                'end_date' => '2027-06-30',
            ])
            ->assertRedirect(route('workspace.training.index', $workspace));

        $this->assertDatabaseHas('training_programs', [
            'workspace_id' => $workspace->id,
            'organization_id' => $organization->id,
            'name' => 'Programme DWWM',
            'certification' => 'DWWM',
        ]);

        $this->assertTrue(
            AuditLog::query()->where('action', 'training_program.created')->exists()
        );
    }

    public function test_trainee_cannot_create_a_training_program(): void
    {
        $trainee = User::factory()->create();
        $workspace = $this->workspaceWithMember($trainee, 'trainee');
        $organization = Organization::factory()->create(['workspace_id' => $workspace->id]);

        $this->actingAs($trainee)
            ->post(route('workspace.training.store', $workspace), [
                'organization_id' => $organization->id,
                'name' => 'Interdit',
            ])
            ->assertForbidden();

        $this->assertSame(0, TrainingProgram::count());
    }

    public function test_training_program_is_isolated_between_workspaces(): void
    {
        $ownerA = User::factory()->create();
        $workspaceA = $this->workspaceWithMember($ownerA);
        $programA = TrainingProgram::factory()->create(['workspace_id' => $workspaceA->id]);

        $ownerB = User::factory()->create();
        $workspaceB = $this->workspaceWithMember($ownerB);

        $this->actingAs($ownerB)
            ->get(route('workspace.training.edit', [$workspaceB, $programA]))
            ->assertNotFound();
    }

    public function test_enrollment_is_unique_per_user_and_program(): void
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
        $program = TrainingProgram::factory()->create([
            'workspace_id' => $workspace->id,
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.training.enrollments.store', [$workspace, $program]), [
                'user_id' => $trainee->id,
                'start_date' => '2026-09-01',
                'end_date' => '2027-06-30',
            ])
            ->assertRedirect();

        $this->assertSame(1, $program->enrollments()->count());

        $this->actingAs($owner)
            ->post(route('workspace.training.enrollments.store', [$workspace, $program]), [
                'user_id' => $trainee->id,
                'start_date' => '2026-09-01',
                'end_date' => '2027-06-30',
            ])
            ->assertSessionHasErrors('user_id');

        $this->assertSame(1, $program->enrollments()->count());
    }

    public function test_enrollment_rejects_non_member(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);
        $stranger = User::factory()->create();

        $organization = Organization::factory()->create(['workspace_id' => $workspace->id]);
        $program = TrainingProgram::factory()->create([
            'workspace_id' => $workspace->id,
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.training.enrollments.store', [$workspace, $program]), [
                'user_id' => $stranger->id,
                'start_date' => '2026-09-01',
                'end_date' => '2027-06-30',
            ])
            ->assertSessionHasErrors('user_id');

        $this->assertSame(0, $program->enrollments()->count());
    }
}
