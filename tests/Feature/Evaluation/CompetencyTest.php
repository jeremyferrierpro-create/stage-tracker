<?php

namespace Tests\Feature\Evaluation;

use App\Domain\Evaluation\Models\ActivityCompetency;
use App\Domain\Evaluation\Models\CompetencyAssessment;
use App\Domain\Framework\Models\Competency;
use App\Domain\Framework\Models\CompetencyBlock;
use App\Domain\Framework\Models\CompetencyFramework;
use App\Domain\Framework\Models\FrameworkVersion;
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
 * T-060 / T-061 — compétences et évaluations nominatives.
 * INV-09 (aucune validation automatique), AC-11.1, AC-11.2.
 */
class CompetencyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private Internship $internship;

    private Project $project;

    private Activity $activity;

    private Competency $competency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->workspace = Workspace::factory()->create(['owner_id' => $this->owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->owner->id,
            'role' => 'owner',
        ]);

        $framework = CompetencyFramework::factory()->create();
        $version = FrameworkVersion::factory()->create([
            'framework_id' => $framework->id,
            'status' => 'draft',
        ]);
        $block = CompetencyBlock::query()->create([
            'framework_version_id' => $version->id,
            'code' => 'B1',
            'title' => 'Bloc démo',
            'position' => 1,
        ]);
        $this->competency = Competency::query()->create([
            'framework_version_id' => $version->id,
            'block_id' => $block->id,
            'code' => 'C1.1',
            'title' => 'Rédiger un message professionnel',
            'position' => 1,
        ]);

        $this->internship = Internship::factory()->create([
            'workspace_id' => $this->workspace->id,
            'framework_version_id' => $version->id,
        ]);
        $this->project = Project::factory()->create([
            'internship_id' => $this->internship->id,
            'workspace_id' => $this->workspace->id,
        ]);
        $this->activity = Activity::factory()->create([
            'project_id' => $this->project->id,
            'workspace_id' => $this->workspace->id,
            'title' => 'Rédiger le message client',
        ]);
    }

    /**
     * Participant actif d'un rôle donné sur le stage démo.
     */
    private function participant(string $workspaceRole, string $stageRole): User
    {
        $user = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $user->id,
            'role' => $workspaceRole,
        ]);
        InternshipParticipant::query()->create([
            'internship_id' => $this->internship->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $user->id,
            'role' => $stageRole,
            'permissions' => [],
            'status' => 'active',
        ]);

        return $user;
    }

    private function associate(User $actor, ?string $justification = 'Activité mobilisant la compétence visée.'): ActivityCompetency
    {
        $this->actingAs($actor)
            ->post(route('workspace.internships.projects.activities.competencies.store', [
                $this->workspace, $this->internship, $this->project, $this->activity,
            ]), [
                'competency_id' => $this->competency->id,
                'justification' => $justification,
            ])
            ->assertRedirect();

        return ActivityCompetency::query()->sole();
    }

    public function test_trainee_associates_competency_with_required_justification(): void
    {
        $this->actingAs($this->owner)
            ->post(route('workspace.internships.projects.activities.competencies.store', [
                $this->workspace, $this->internship, $this->project, $this->activity,
            ]), [
                'competency_id' => $this->competency->id,
                'justification' => 'court',
            ])
            ->assertSessionHasErrors('justification');

        $association = $this->associate($this->owner);

        $this->assertSame('mobilized', $association->status);
        $this->assertNull($association->documented_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'competency.associated']);
    }

    public function test_competency_must_belong_to_the_internship_framework(): void
    {
        $foreignVersion = FrameworkVersion::factory()->create([
            'framework_id' => CompetencyFramework::factory()->create()->id,
            'status' => 'draft',
        ]);
        $foreign = Competency::query()->create([
            'framework_version_id' => $foreignVersion->id,
            'code' => 'X9.9',
            'title' => 'Compétence hors référentiel',
            'position' => 1,
        ]);

        $this->actingAs($this->owner)
            ->post(route('workspace.internships.projects.activities.competencies.store', [
                $this->workspace, $this->internship, $this->project, $this->activity,
            ]), [
                'competency_id' => $foreign->id,
                'justification' => 'Justification hors référentiel du stage.',
            ])
            ->assertNotFound();

        $this->assertSame(0, ActivityCompetency::query()->count());
    }

    public function test_duplicate_association_is_rejected(): void
    {
        $this->associate($this->owner);

        $this->actingAs($this->owner)
            ->post(route('workspace.internships.projects.activities.competencies.store', [
                $this->workspace, $this->internship, $this->project, $this->activity,
            ]), [
                'competency_id' => $this->competency->id,
                'justification' => 'Deuxième association refusée.',
            ])
            ->assertSessionHasErrors('competency_id');

        $this->assertSame(1, ActivityCompetency::query()->count());
    }

    public function test_supervisor_cannot_associate(): void
    {
        $supervisor = $this->participant('supervisor', 'supervisor');

        $this->actingAs($supervisor)
            ->post(route('workspace.internships.projects.activities.competencies.store', [
                $this->workspace, $this->internship, $this->project, $this->activity,
            ]), [
                'competency_id' => $this->competency->id,
                'justification' => 'Le maître de stage ne gère pas l\'activité.',
            ])
            ->assertForbidden();

        $this->assertSame(0, ActivityCompetency::query()->count());
    }

    public function test_manager_can_mark_documented(): void
    {
        $association = $this->associate($this->owner);

        $this->actingAs($this->owner)
            ->post(route('workspace.internships.projects.activities.competencies.document', [
                $this->workspace, $this->internship, $this->project, $this->activity, $association,
            ]))
            ->assertRedirect();

        $association->refresh();
        $this->assertSame('documented', $association->status);
        $this->assertNotNull($association->documented_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'competency.documented']);
    }

    public function test_ac_11_1_closing_activity_does_not_validate_competencies(): void
    {
        $association = $this->associate($this->owner);

        // Aucun évaluateur n'intervient : on clôture l'activité.
        $this->activity->forceFill(['status' => 'done'])->save();

        $association->refresh();
        $this->assertSame('mobilized', $association->status);
        $this->assertSame(0, CompetencyAssessment::query()->count());
        $this->assertDatabaseMissing('activity_competencies', ['id' => $association->id, 'status' => 'evaluated']);
    }

    public function test_ac_11_2_tutor_assessment_is_traced_and_validates(): void
    {
        $tutor = $this->participant('tutor', 'tutor');
        $association = $this->associate($this->owner);

        $this->actingAs($tutor)
            ->post(route('workspace.internships.projects.activities.competencies.assess', [
                $this->workspace, $this->internship, $this->project, $this->activity, $association,
            ]), [
                'status' => 'validated',
                'appreciation' => 'Message client clair, structuré et adapté au destinataire.',
            ])
            ->assertRedirect();

        $assessment = CompetencyAssessment::query()->sole();

        // Évaluateur, date et appréciation conservés (AC-11.2).
        $this->assertSame($tutor->id, $assessment->assessor_id);
        $this->assertNotNull($assessment->assessed_at);
        $this->assertSame('Message client clair, structuré et adapté au destinataire.', $assessment->appreciation);

        // La compétence passe à `evaluated` par action explicite habilitée.
        $this->assertSame('evaluated', $association->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'assessment.created']);
    }

    public function test_trainee_cannot_self_assess(): void
    {
        // Le stagiaire est membre workspace `trainee` et participant du stage.
        $this->associate($this->owner);
        $association = ActivityCompetency::query()->sole();

        $this->actingAs($this->internship->trainee)
            ->post(route('workspace.internships.projects.activities.competencies.assess', [
                $this->workspace, $this->internship, $this->project, $this->activity, $association,
            ]), [
                'status' => 'validated',
                'appreciation' => 'Auto-évaluation interdite par la matrice.',
            ])
            ->assertForbidden();

        $this->assertSame(0, CompetencyAssessment::query()->count());
        $this->assertSame('mobilized', $association->fresh()->status);
    }

    public function test_owner_cannot_assess(): void
    {
        $association = $this->associate($this->owner);

        $this->actingAs($this->owner)
            ->post(route('workspace.internships.projects.activities.competencies.assess', [
                $this->workspace, $this->internship, $this->project, $this->activity, $association,
            ]), [
                'status' => 'validated',
                'appreciation' => 'Le propriétaire n\'évalue pas (matrice).',
            ])
            ->assertForbidden();

        $this->assertSame(0, CompetencyAssessment::query()->count());
    }

    public function test_supervisor_participant_can_assess(): void
    {
        $supervisor = $this->participant('supervisor', 'supervisor');
        $association = $this->associate($this->owner);

        $this->actingAs($supervisor)
            ->post(route('workspace.internships.projects.activities.competencies.assess', [
                $this->workspace, $this->internship, $this->project, $this->activity, $association,
            ]), [
                'status' => 'rejected',
                'appreciation' => 'Le message manque de structure.',
            ])
            ->assertRedirect();

        $assessment = CompetencyAssessment::query()->sole();
        $this->assertSame('rejected', $assessment->status);

        // Rejet : la compétence ne passe PAS à évaluée.
        $this->assertSame('mobilized', $association->fresh()->status);
    }

    public function test_supervisor_without_assignment_cannot_assess(): void
    {
        $unassigned = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $unassigned->id,
            'role' => 'supervisor',
        ]);
        $association = $this->associate($this->owner);

        $this->actingAs($unassigned)
            ->post(route('workspace.internships.projects.activities.competencies.assess', [
                $this->workspace, $this->internship, $this->project, $this->activity, $association,
            ]), [
                'status' => 'validated',
                'appreciation' => 'Non rattaché au stage.',
            ])
            ->assertForbidden();

        $this->assertSame(0, CompetencyAssessment::query()->count());
    }

    public function test_evaluated_association_cannot_be_removed(): void
    {
        $tutor = $this->participant('tutor', 'tutor');
        $association = $this->associate($this->owner);

        $this->actingAs($tutor)
            ->post(route('workspace.internships.projects.activities.competencies.assess', [
                $this->workspace, $this->internship, $this->project, $this->activity, $association,
            ]), [
                'status' => 'validated',
                'appreciation' => 'Validée par le tuteur pédagogique.',
            ])
            ->assertRedirect();

        $this->actingAs($this->owner)
            ->delete(route('workspace.internships.projects.activities.competencies.destroy', [
                $this->workspace, $this->internship, $this->project, $this->activity, $association,
            ]))
            ->assertSessionHasErrors('competency');

        $this->assertDatabaseHas('activity_competencies', ['id' => $association->id, 'status' => 'evaluated']);
    }

    public function test_mobilized_association_can_be_removed_and_is_audited(): void
    {
        $association = $this->associate($this->owner);

        $this->actingAs($this->owner)
            ->delete(route('workspace.internships.projects.activities.competencies.destroy', [
                $this->workspace, $this->internship, $this->project, $this->activity, $association,
            ]))
            ->assertRedirect();

        $this->assertSame(0, ActivityCompetency::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'competency.unlinked']);
    }

    public function test_competency_matrix_is_scoped_to_the_internship(): void
    {
        $tutor = $this->participant('tutor', 'tutor');
        $this->associate($this->owner);

        // Un autre stage du même workspace.
        $otherInternship = Internship::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);

        $this->actingAs($tutor)
            ->get(route('workspace.internships.competencies', [$this->workspace, $this->internship]))
            ->assertOk()
            ->assertSee('C1.1');

        // Stage non rattaché : le tuteur n'a pas accès à la matrice.
        $this->actingAs($tutor)
            ->get(route('workspace.internships.competencies', [$this->workspace, $otherInternship]))
            ->assertForbidden();
    }

    public function test_closing_a_daily_log_does_not_touch_competencies(): void
    {
        $association = $this->associate($this->owner);

        $log = DailyLog::query()->create([
            'internship_id' => $this->internship->id,
            'workspace_id' => $this->workspace->id,
            'author_id' => $this->internship->trainee_id,
            'work_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'duration_minutes' => 480,
            'objectives' => 'Clôture journal.',
            'work_done' => 'Journée de travail terminée.',
            'status' => 'draft',
        ]);
        $log->forceFill(['status' => 'closed'])->save();

        $this->assertSame('mobilized', $association->fresh()->status);
        $this->assertSame(0, CompetencyAssessment::query()->count());
    }
}
