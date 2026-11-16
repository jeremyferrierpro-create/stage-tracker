<?php

namespace Tests\Feature\Collaboration;

use App\Domain\Collaboration\Models\Review;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Internship\Models\InternshipParticipant;
use App\Domain\Project\Models\Activity;
use App\Domain\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
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

        $tutor = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $tutor->id,
            'role' => 'tutor',
        ]);

        $internship = Internship::factory()->create(['workspace_id' => $workspace->id]);
        $supervisorParticipant = InternshipParticipant::query()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'user_id' => $supervisor->id,
            'role' => 'supervisor',
            'permissions' => [],
            'status' => 'active',
        ]);
        InternshipParticipant::query()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'user_id' => $tutor->id,
            'role' => 'tutor',
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

        return [$workspace, $internship, $project, $activity, $owner, $supervisor, $tutor, $supervisorParticipant];
    }

    public function test_trainee_requests_review_from_supervisor(): void
    {
        [$workspace, $internship, $project, $activity, $owner, $supervisor] = $this->context();

        $this->actingAs($owner)
            ->post(route('workspace.internships.projects.activities.reviews.store', [$workspace, $internship, $project, $activity]), [
                'reviewer_id' => $supervisor->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $review = Review::query()->sole();

        $this->assertSame('requested', $review->status);
        $this->assertSame($supervisor->id, $review->reviewer_id);
    }

    public function test_reviewer_cannot_be_an_outsider(): void
    {
        [$workspace, $internship, $project, $activity, $owner] = $this->context();

        $outsider = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('workspace.internships.projects.activities.reviews.store', [$workspace, $internship, $project, $activity]), [
                'reviewer_id' => $outsider->id,
            ])
            ->assertSessionHasErrors('reviewer_id');

        $this->assertSame(0, Review::query()->count());
    }

    public function test_only_the_reviewer_can_submit_feedback(): void
    {
        [$workspace, $internship, $project, $activity, $owner, $supervisor, $tutor] = $this->context();

        $review = Review::factory()->create([
            'activity_id' => $activity->id,
            'workspace_id' => $workspace->id,
            'reviewer_id' => $supervisor->id,
            'status' => 'requested',
        ]);

        // Le tuteur n'est pas le réviseur de cette revue.
        $this->actingAs($tutor)
            ->post(route('workspace.internships.projects.activities.reviews.feedback', [$workspace, $internship, $project, $activity, $review]), [
                'status' => 'in_progress',
            ])
            ->assertForbidden();

        // Le stagiaire non plus.
        $this->actingAs($owner)
            ->post(route('workspace.internships.projects.activities.reviews.feedback', [$workspace, $internship, $project, $activity, $review]), [
                'status' => 'approved',
            ])
            ->assertForbidden();

        // Le réviseur lui-même : demandée → en cours.
        $this->actingAs($supervisor)
            ->post(route('workspace.internships.projects.activities.reviews.feedback', [$workspace, $internship, $project, $activity, $review]), [
                'status' => 'in_progress',
                'feedback' => 'Je regarde la maquette cette semaine.',
            ])
            ->assertRedirect();

        $review->refresh();
        $this->assertSame('in_progress', $review->status);
        $this->assertSame('Je regarde la maquette cette semaine.', $review->feedback);
    }

    public function test_status_transitions_are_coherent(): void
    {
        [$workspace, $internship, $project, $activity, $owner, $supervisor] = $this->context();

        $review = Review::factory()->create([
            'activity_id' => $activity->id,
            'workspace_id' => $workspace->id,
            'reviewer_id' => $supervisor->id,
            'status' => 'requested',
        ]);

        $url = route('workspace.internships.projects.activities.reviews.feedback', [$workspace, $internship, $project, $activity, $review]);

        // demandée → modifications demandées : autorisé.
        $this->actingAs($supervisor)
            ->post($url, ['status' => 'changes_requested', 'feedback' => 'Corriger les contrastes.'])
            ->assertRedirect();

        // modifications demandées → approuvée : autorisé.
        $this->actingAs($supervisor)
            ->post($url, ['status' => 'approved', 'feedback' => 'Corrigé et validé.'])
            ->assertRedirect();

        // approuvée est un état final : plus aucune transition.
        $this->actingAs($supervisor)
            ->post($url, ['status' => 'in_progress'])
            ->assertSessionHasErrors('review');

        $review->refresh();
        $this->assertSame('approved', $review->status);
        $this->assertSame('Corrigé et validé.', $review->feedback);
    }

    public function test_requesting_review_requires_activity_management(): void
    {
        [$workspace, $internship, $project, $activity, $owner, $supervisor] = $this->context();

        // Un maître de stage ne peut pas demander une revue.
        $this->actingAs($supervisor)
            ->post(route('workspace.internships.projects.activities.reviews.store', [$workspace, $internship, $project, $activity]), [
                'reviewer_id' => $supervisor->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, Review::query()->count());
    }

    public function test_requests_and_updates_are_audited(): void
    {
        [$workspace, $internship, $project, $activity, $owner, $supervisor] = $this->context();

        $this->actingAs($owner)
            ->post(route('workspace.internships.projects.activities.reviews.store', [$workspace, $internship, $project, $activity]), [
                'reviewer_id' => $supervisor->id,
            ])
            ->assertRedirect();

        $review = Review::query()->sole();

        $this->actingAs($supervisor)
            ->post(route('workspace.internships.projects.activities.reviews.feedback', [$workspace, $internship, $project, $activity, $review]), [
                'status' => 'approved',
                'feedback' => 'OK.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'review.requested']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'review.updated']);
    }
}
