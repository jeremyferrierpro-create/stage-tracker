<?php

namespace Tests\Feature\Identity;

use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class TutorInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_trainee_can_invite_supervisor_and_receives_magic_link_in_session(): void
    {
        Mail::fake();

        $trainee = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $trainee->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $trainee->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $response = $this->actingAs($trainee)
            ->post(route('workspace.invitations.store', $workspace), [
                'email' => 'tuteur@entreprise.test',
                'role' => 'supervisor',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Invitation créée avec succès.');
        $response->assertSessionHas('invitation_link');

        $link = session('invitation_link');
        $this->assertNotNull($link);
        $this->assertStringContainsString('/invitations/', $link);

        $this->assertDatabaseHas('invitations', [
            'workspace_id' => $workspace->id,
            'email' => 'tuteur@entreprise.test',
            'role' => 'supervisor',
        ]);
    }

    public function test_supervisor_registering_via_magic_link_is_automatically_attached_to_internship(): void
    {
        Mail::fake();

        $trainee = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $trainee->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $trainee->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $internship = Internship::factory()->create([
            'workspace_id' => $workspace->id,
            'trainee_id' => $trainee->id,
            'title' => 'Stage DWWM',
            'status' => 'active',
        ]);

        // Generate invitation
        $token = Str::random(48);
        $invitation = Invitation::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'tuteur@entreprise.test',
            'role' => 'supervisor',
            'token_hash' => Invitation::hashToken($token),
            'expires_at' => now()->addDays(7),
            'created_by' => $trainee->id,
        ]);

        // Supervisor registers via magic link
        $response = $this->post(route('invitations.register', $token), [
            'name' => 'Maître de Stage',
            'password' => 'secret-password-123',
            'password_confirmation' => 'secret-password-123',
        ]);

        $response->assertRedirect(route('workspace.dashboard', $workspace));

        $supervisor = User::where('email', 'tuteur@entreprise.test')->first();
        $this->assertNotNull($supervisor);

        // Assert member of workspace
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $supervisor->id,
            'role' => 'supervisor',
            'status' => 'active',
        ]);

        // Assert attached to internship participant
        $this->assertDatabaseHas('internship_participants', [
            'workspace_id' => $workspace->id,
            'internship_id' => $internship->id,
            'user_id' => $supervisor->id,
            'role' => 'supervisor',
            'status' => 'active',
        ]);
    }

    public function test_strict_isolation_between_different_trainee_tutor_pairs(): void
    {
        // Pair A
        $traineeA = User::factory()->create();
        $workspaceA = Workspace::factory()->create(['owner_id' => $traineeA->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspaceA->id,
            'user_id' => $traineeA->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        // Pair B
        $traineeB = User::factory()->create();
        $workspaceB = Workspace::factory()->create(['owner_id' => $traineeB->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspaceB->id,
            'user_id' => $traineeB->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        // Trainee A cannot view Workspace B dashboard
        $responseA = $this->actingAs($traineeA)->get(route('workspace.dashboard', $workspaceB));
        $responseA->assertForbidden();

        // Trainee B cannot view Workspace A dashboard
        $responseB = $this->actingAs($traineeB)->get(route('workspace.dashboard', $workspaceA));
        $responseB->assertForbidden();
    }
}
