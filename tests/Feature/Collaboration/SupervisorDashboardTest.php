<?php

namespace Tests\Feature\Collaboration;

use App\Domain\Collaboration\Models\Review;
use App\Domain\Evidence\Models\Evidence;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Internship\Models\InternshipParticipant;
use App\Domain\Journal\Models\DailyLog;
use App\Domain\Project\Models\Activity;
use App\Domain\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-054 — vue dédiée maître de stage : périmètre limité, aucune donnée
 * privée affichée (aucune fuite par métadonnées).
 */
class SupervisorDashboardTest extends TestCase
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
        InternshipParticipant::query()->create([
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
            'title' => 'Maquette du formulaire',
        ]);

        $sharedEvidence = Evidence::query()->create([
            'workspace_id' => $workspace->id,
            'owner_id' => $internship->trainee_id,
            'title' => 'Capture partagée de la maquette',
            'type' => 'screenshot',
            'demonstrated' => 'Maquette validée.',
            'external_url' => 'https://example.test/maquette',
            'visibility' => 'shared',
        ]);
        $privateEvidence = Evidence::query()->create([
            'workspace_id' => $workspace->id,
            'owner_id' => $internship->trainee_id,
            'title' => 'NOTES PRIVÉES SECRETES DU STAGIAIRE',
            'type' => 'technical_doc',
            'demonstrated' => 'Démarche personnelle.',
            'external_url' => 'https://example.test/prive',
            'visibility' => 'private',
        ]);
        $activity->evidences()->attach([$sharedEvidence->id, $privateEvidence->id], [
            'workspace_id' => $workspace->id,
            'note' => null,
        ]);

        $review = Review::query()->create([
            'activity_id' => $activity->id,
            'workspace_id' => $workspace->id,
            'reviewer_id' => $supervisor->id,
            'status' => 'requested',
        ]);

        DailyLog::query()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'author_id' => $internship->trainee_id,
            'work_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'duration_minutes' => 480,
            'objectives' => 'Terminer la maquette.',
            'work_done' => 'Intégration de la maquette du formulaire.',
            'difficulties' => null,
            'solutions' => null,
            'results' => null,
            'remaining' => null,
            'private_notes' => 'JOURNAL PRIVE NE DOIT PAS APPARAITRE',
            'status' => 'closed',
        ]);

        return [$workspace, $internship, $project, $activity, $owner, $supervisor];
    }

    public function test_dashboard_shows_assigned_internships_without_private_data(): void
    {
        [$workspace, $internship, $project, $activity, $owner, $supervisor] = $this->context();

        $this->actingAs($supervisor)
            ->get(route('workspace.supervisor.dashboard', $workspace))
            ->assertOk()
            ->assertSee($internship->title)
            ->assertSee('Maquette du formulaire')
            ->assertSee('Capture partagée de la maquette')
            ->assertSee('Intégration de la maquette du formulaire.')
            // Aucune donnée privée (titre de preuve privée, private_notes).
            ->assertDontSee('NOTES PRIVÉES SECRETES DU STAGIAIRE')
            ->assertDontSee('JOURNAL PRIVE NE DOIT PAS APPARAITRE');
    }

    public function test_pending_reviews_are_listed(): void
    {
        [$workspace, $internship, $project, $activity, $owner, $supervisor] = $this->context();

        $this->actingAs($supervisor)
            ->get(route('workspace.supervisor.dashboard', $workspace))
            ->assertOk()
            ->assertSee('Maquette du formulaire')
            ->assertSee('Demandée');
    }

    public function test_supervisor_without_assignment_sees_an_empty_dashboard(): void
    {
        [$workspace, $internship, $project, $activity, $owner, $supervisor] = $this->context();

        $unassigned = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $unassigned->id,
            'role' => 'supervisor',
        ]);

        $this->actingAs($unassigned)
            ->get(route('workspace.supervisor.dashboard', $workspace))
            ->assertOk()
            ->assertSee('Aucun stage actif vous est rattaché');

        // Aucune donnée de l'autre stage ne fuit (MT-07/MT-10).
        $this->actingAs($unassigned)
            ->get(route('workspace.supervisor.dashboard', $workspace))
            ->assertDontSee($internship->title);
    }

    public function test_other_roles_cannot_access_the_dashboard(): void
    {
        [$workspace, $internship, $project, $activity, $owner, $supervisor] = $this->context();

        // Le dashboard est réservé aux maîtres de stage / tuteurs.
        $this->actingAs($owner)
            ->get(route('workspace.supervisor.dashboard', $workspace))
            ->assertForbidden();

        $visitor = User::factory()->create();
        $visitorWorkspace = Workspace::factory()->create(['owner_id' => $visitor->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $visitorWorkspace->id,
            'user_id' => $visitor->id,
            'role' => 'owner',
        ]);

        $this->actingAs($visitor)
            ->get(route('workspace.supervisor.dashboard', $workspace))
            ->assertForbidden();
    }
}
