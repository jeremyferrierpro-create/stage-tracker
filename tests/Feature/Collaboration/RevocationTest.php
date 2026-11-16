<?php

namespace Tests\Feature\Collaboration;

use App\Domain\Collaboration\Models\Comment;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Internship\Models\InternshipParticipant;
use App\Domain\Project\Models\Activity;
use App\Domain\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MT-10 (complet) — révocation d'un participant et conservation de
 * l'historique (INV-13) : aucune cascade destructive.
 */
class RevocationTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        $supervisor = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $supervisor->id,
            'role' => 'supervisor',
        ]);

        $internship = Internship::factory()->create(['workspace_id' => $workspace->id]);
        $participant = InternshipParticipant::query()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'user_id' => $supervisor->id,
            'role' => 'supervisor',
            'permissions' => [],
            'status' => 'active',
        ]);

        $project = Project::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
        ]);
        $activity = Activity::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $workspace->id,
        ]);

        $comment = Comment::query()->create([
            'activity_id' => $activity->id,
            'workspace_id' => $workspace->id,
            'author_id' => $supervisor->id,
            'content' => 'Retour du maître de stage avant révocation.',
            'visibility' => 'stage',
        ]);

        return [$workspace, $internship, $project, $activity, $owner, $supervisor, $participant, $comment];
    }

    public function test_revocation_removes_access_but_keeps_history(): void
    {
        [$workspace, $internship, $project, $activity, $owner, $supervisor, $participant, $comment] = $this->context();

        $this->actingAs($owner)
            ->delete(route('workspace.internships.participants.destroy', [$workspace, $internship, $participant]))
            ->assertRedirect()
            ->assertSessionHas('status');

        // L'accès est révoqué immédiatement.
        $this->actingAs($supervisor)
            ->get(route('workspace.internships.show', [$workspace, $internship]))
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->post(route('workspace.internships.projects.activities.comments.store', [$workspace, $internship, $project, $activity]), [
                'content' => 'Tentative après révocation.',
                'visibility' => 'stage',
            ])
            ->assertForbidden();

        // L'historique du commentateur est conservé (INV-13).
        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'author_id' => $supervisor->id,
            'content' => 'Retour du maître de stage avant révocation.',
        ]);

        // Aucune cascade destructive : l'utilisateur existe toujours.
        $this->assertDatabaseHas('users', ['id' => $supervisor->id]);
        $this->assertDatabaseHas('workspace_members', ['user_id' => $supervisor->id, 'role' => 'supervisor']);
        $this->assertDatabaseHas('activities', ['id' => $activity->id]);

        // Le participant reste tracé, avec le statut removed.
        $this->assertDatabaseHas('internship_participants', [
            'id' => $participant->id,
            'status' => 'removed',
        ]);

        // Les ayants droit lisent toujours l'historique du commentateur.
        $this->actingAs($owner)
            ->get(route('workspace.internships.projects.activities.show', [$workspace, $internship, $project, $activity]))
            ->assertOk()
            ->assertSee('Retour du maître de stage avant révocation.');

        // La révocation est journalisée.
        $this->assertDatabaseHas('audit_logs', ['action' => 'internship.participant.revoked']);
    }

    public function test_reactivation_restores_access(): void
    {
        [$workspace, $internship, $project, $activity, $owner, $supervisor, $participant] = $this->context();

        $this->actingAs($owner)
            ->delete(route('workspace.internships.participants.destroy', [$workspace, $internship, $participant]))
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('workspace.internships.participants.reactivate', [$workspace, $internship, $participant]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('internship_participants', [
            'id' => $participant->id,
            'status' => 'active',
        ]);

        $this->actingAs($supervisor)
            ->get(route('workspace.internships.show', [$workspace, $internship]))
            ->assertOk();
    }

    public function test_disabled_account_cannot_login_but_history_remains_readable(): void
    {
        [$workspace, $internship, $project, $activity, $owner, $supervisor, $participant, $comment] = $this->context();

        $supervisor->forceFill(['status' => 'disabled'])->save();

        // Le compte désactivé ne peut plus se connecter.
        $this->post('/login', [
            'email' => $supervisor->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        // Son historique reste lisible par les ayants droit.
        $this->actingAs($owner)
            ->get(route('workspace.internships.projects.activities.show', [$workspace, $internship, $project, $activity]))
            ->assertOk()
            ->assertSee('Retour du maître de stage avant révocation.');

        $this->assertDatabaseHas('users', ['id' => $supervisor->id, 'status' => 'disabled']);
    }

    public function test_trainee_cannot_be_revoked(): void
    {
        [$workspace, $internship, $project, $activity, $owner] = $this->context();

        $trainee = InternshipParticipant::query()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'user_id' => $internship->trainee_id,
            'role' => 'trainee',
            'permissions' => [],
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->delete(route('workspace.internships.participants.destroy', [$workspace, $internship, $trainee]))
            ->assertSessionHasErrors('participant');

        $this->assertDatabaseHas('internship_participants', [
            'id' => $trainee->id,
            'status' => 'active',
        ]);
    }

    public function test_non_manager_cannot_revoke(): void
    {
        [$workspace, $internship, $project, $activity, $owner, $supervisor, $participant] = $this->context();

        $this->actingAs($supervisor)
            ->delete(route('workspace.internships.participants.destroy', [$workspace, $internship, $participant]))
            ->assertForbidden();

        $this->assertDatabaseHas('internship_participants', [
            'id' => $participant->id,
            'status' => 'active',
        ]);
    }
}
