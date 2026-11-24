<?php

namespace Tests\Feature\Planning;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Internship\Models\InternshipParticipant;
use App\Domain\Journal\Models\DailyLog;
use App\Domain\Planning\Models\PlanEntry;
use App\Domain\Planning\PlanningComparisonService;
use App\Domain\Project\Models\Activity;
use App\Domain\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PlanningTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceWithOwner(User $owner): Workspace
    {
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        return $workspace;
    }

    private function internshipWithProject(Workspace $workspace, User $trainee): array
    {
        $internship = Internship::factory()->create([
            'workspace_id' => $workspace->id,
            'trainee_id' => $trainee->id,
        ]);
        $project = Project::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'name' => 'COAB',
        ]);

        return [$internship, $project];
    }

    private function fixturePath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'planning').'.md';
        file_put_contents($path, <<<'MD'
# Planning test

## Semaine 1

| Jour | Matin (9h-12h30) | Après-midi (13h30-17h) |
|---|---|---|
| **Lun 23/11** | Finaliser docs ; squelette MVC | Page d'accueil + layout |
| **Mar 24/11** | Config env | — |
| **Mer 25/11** | **Jour light** — repos | — |
MD);

        return $path;
    }

    public function test_import_command_creates_plan_entries(): void
    {
        $owner = User::factory()->create(['email' => 'jeremy.ferrierpro@gmail.com']);
        $workspace = $this->workspaceWithOwner($owner);
        $trainee = User::factory()->create();
        [$internship] = $this->internshipWithProject($workspace, $trainee);

        $file = $this->fixturePath();

        $this->artisan('stage:import-planning', [
            'file' => $file,
            '--email' => 'jeremy.ferrierpro@gmail.com',
            '--project' => 'COAB',
        ])->assertSuccessful();

        // Lun 23/11 : 2 sous-tâches le matin + 2 l'après-midi = 4 entrées.
        // Mar 24/11 : 1 entrée. Mer 25/11 : 1 entrée. Total = 6.
        $this->assertSame(6, PlanEntry::count());

        $this->assertDatabaseHas('plan_entries', [
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'plan_date' => '2026-11-23',
            'slot' => 'morning',
            'title' => 'Finaliser docs',
            'planned_minutes' => 105,
            'position' => 0,
        ]);
        $this->assertDatabaseHas('plan_entries', [
            'plan_date' => '2026-11-23',
            'slot' => 'afternoon',
            'title' => 'Page d\'accueil',
            'planned_minutes' => 105,
        ]);
        $this->assertDatabaseHas('plan_entries', [
            'plan_date' => '2026-11-24',
            'slot' => 'morning',
            'title' => 'Config env',
            'planned_minutes' => 210,
        ]);

        // Le projet COAB est rattaché.
        $this->assertSame(
            Project::where('name', 'COAB')->value('id'),
            PlanEntry::where('plan_date', '2026-11-23')->value('project_id'),
        );

        unlink($file);
    }

    public function test_parser_handles_four_columns_and_skips_non_working_rows(): void
    {
        $entries = \App\Domain\Planning\PlanningMarkdownParser::parse(<<<'MD'
| Jour | Matin | Après-midi | Livrable / note |
|---|---|---|---|
| Lun 23/11 | Tâche A + Tâche B | — | Note ignorée |
| Mar 24/11 | **VACANCES** | | |
| Mer 25/11 | Injection AIDF 0.4.0 + npm | Revue | — |
MD);

        // Lun : 2 sous-tâches le matin, après-midi « — » ignoré.
        // Mar : « VACANCES » ignoré. Mer : « + npm » non scindé (côté < 6 car.).
        $this->assertCount(4, $entries);
        $this->assertSame('2026-11-23', $entries[0]['plan_date']);
        $this->assertSame('Tâche A', $entries[0]['title']);
        $this->assertSame('Injection AIDF 0.4.0 + npm', $entries[2]['title']);
        $this->assertSame(210, $entries[2]['planned_minutes']);
    }

    public function test_import_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithOwner($owner);
        $trainee = User::factory()->create();
        $this->internshipWithProject($workspace, $trainee);

        $file = $this->fixturePath();
        $args = ['file' => $file, '--workspace' => $workspace->id];

        Artisan::call('stage:import-planning', $args);
        Artisan::call('stage:import-planning', $args);

        $this->assertSame(6, PlanEntry::count());

        unlink($file);
    }

    public function test_comparison_statuses(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithOwner($owner);
        $trainee = User::factory()->create();
        [$internship] = $this->internshipWithProject($workspace, $trainee);

        // Jour passé entièrement fait (2 créneaux × 210 min).
        PlanEntry::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'plan_date' => '2026-11-20',
            'slot' => 'morning',
            'planned_minutes' => 210,
        ]);
        PlanEntry::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'plan_date' => '2026-11-20',
            'slot' => 'afternoon',
            'planned_minutes' => 210,
        ]);
        // Jour passé partiel.
        PlanEntry::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'plan_date' => '2026-11-19',
            'slot' => 'morning',
            'planned_minutes' => 210,
        ]);
        // Jour passé sans journal → reporté.
        PlanEntry::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'plan_date' => '2026-11-18',
            'slot' => 'morning',
            'planned_minutes' => 210,
        ]);
        // Jour futur sans journal → à faire.
        PlanEntry::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'plan_date' => '2027-01-15',
            'slot' => 'morning',
            'planned_minutes' => 210,
        ]);

        DailyLog::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'author_id' => $trainee->id,
            'work_date' => '2026-11-20',
            'duration_minutes' => 420,
            'work_done' => 'Tout le programme',
        ]);
        DailyLog::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'author_id' => $trainee->id,
            'work_date' => '2026-11-19',
            'duration_minutes' => 60,
            'work_done' => 'Un peu de travail',
        ]);

        $days = collect(app(PlanningComparisonService::class)->daysFor($internship))
            ->keyBy('date');

        $this->assertSame('done', $days['2026-11-20']['status']);
        $this->assertSame(100, $days['2026-11-20']['percent']);
        $this->assertSame('partial', $days['2026-11-19']['status']);
        $this->assertSame('missed', $days['2026-11-18']['status']);
        $this->assertSame('todo', $days['2027-01-15']['status']);
    }

    public function test_planning_page_is_visible_to_workspace_member(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithOwner($owner);
        $trainee = User::factory()->create();
        [$internship] = $this->internshipWithProject($workspace, $trainee);

        PlanEntry::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'plan_date' => '2026-11-23',
            'slot' => 'morning',
        ]);

        $this->actingAs($owner)
            ->get(route('workspace.internships.planning.show', [$workspace, $internship]))
            ->assertOk()
            ->assertSee('Planning vs réel')
            ->assertSee('Matin (9h-12h30)');
    }

    public function test_owner_can_create_update_and_delete_plan_entry(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithOwner($owner);
        $trainee = User::factory()->create();
        [$internship, $project] = $this->internshipWithProject($workspace, $trainee);

        // Création.
        $this->actingAs($owner)
            ->post(route('workspace.internships.planning.store', [$workspace, $internship]), [
                'plan_date' => '2026-11-24',
                'slot' => 'morning',
                'title' => 'Squelette MVC',
                'planned_minutes' => 210,
                'project_id' => $project->id,
            ])
            ->assertRedirect();

        $entry = PlanEntry::where('title', 'Squelette MVC')->sole();
        $this->assertSame($workspace->id, $entry->workspace_id);
        $this->assertSame(0, $entry->position);

        // Mise à jour.
        $this->actingAs($owner)
            ->put(route('workspace.internships.planning.update', [$workspace, $internship, $entry]), [
                'plan_date' => '2026-11-25',
                'slot' => 'afternoon',
                'title' => 'Squelette MVC affiné',
                'planned_minutes' => 180,
                'project_id' => null,
            ])
            ->assertRedirect();

        $entry->refresh();
        $this->assertSame('Squelette MVC affiné', $entry->title);
        $this->assertSame('2026-11-25', $entry->plan_date->toDateString());
        $this->assertSame(180, $entry->planned_minutes);

        // Suppression.
        $this->actingAs($owner)
            ->delete(route('workspace.internships.planning.destroy', [$workspace, $internship, $entry]))
            ->assertRedirect();

        $this->assertDatabaseMissing('plan_entries', ['id' => $entry->id]);
    }

    public function test_create_activity_from_plan_entry_links_them(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithOwner($owner);
        $trainee = User::factory()->create();
        [$internship, $project] = $this->internshipWithProject($workspace, $trainee);

        $entry = PlanEntry::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'plan_date' => '2026-11-24',
            'slot' => 'morning',
            'title' => 'Page d\'accueil',
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.internships.planning.activity.store', [$workspace, $internship, $entry]), [
                'project_id' => $project->id,
                'type' => 'feature',
                'status' => 'todo',
                'priority' => 'high',
            ])
            ->assertRedirect();

        $activity = Activity::sole();
        $this->assertSame('Page d\'accueil', $activity->title);
        $this->assertSame($project->id, $activity->project_id);
        $this->assertTrue($entry->fresh()->activities->contains($activity->id));
    }

    public function test_plan_entry_links_competencies_and_activities_on_create_and_update(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithOwner($owner);
        $trainee = User::factory()->create();
        [$internship, $project] = $this->internshipWithProject($workspace, $trainee);

        $competency = \App\Domain\Evaluation\Models\Competency::factory()->create([
            'workspace_id' => $workspace->id,
        ]);
        $activity = Activity::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $workspace->id,
        ]);

        // Création avec liens.
        $this->actingAs($owner)
            ->post(route('workspace.internships.planning.store', [$workspace, $internship]), [
                'plan_date' => '2026-11-24',
                'slot' => 'morning',
                'title' => 'Travail sur les compétences',
                'planned_minutes' => 210,
                'competency_ids' => [$competency->id],
                'activity_ids' => [$activity->id],
            ])
            ->assertRedirect();

        $entry = PlanEntry::where('title', 'Travail sur les compétences')->sole();
        $this->assertTrue($entry->competencies->contains($competency->id));
        $this->assertTrue($entry->activities->contains($activity->id));
        $this->assertDatabaseHas('plan_entry_competencies', [
            'plan_entry_id' => $entry->id,
            'competency_id' => $competency->id,
            'workspace_id' => $workspace->id,
        ]);
        $this->assertDatabaseHas('plan_entry_activities', [
            'plan_entry_id' => $entry->id,
            'activity_id' => $activity->id,
            'workspace_id' => $workspace->id,
        ]);

        // Mise à jour : on délie tout.
        $this->actingAs($owner)
            ->put(route('workspace.internships.planning.update', [$workspace, $internship, $entry]), [
                'plan_date' => '2026-11-24',
                'slot' => 'morning',
                'title' => 'Travail sur les compétences',
                'planned_minutes' => 210,
            ])
            ->assertRedirect();

        $this->assertSame(0, $entry->fresh()->competencies()->count());
        $this->assertSame(0, $entry->fresh()->activities()->count());
    }

    public function test_plan_entry_rejects_foreign_competency(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithOwner($owner);
        $trainee = User::factory()->create();
        [$internship] = $this->internshipWithProject($workspace, $trainee);

        // Compétence d'un AUTRE workspace.
        $foreign = \App\Domain\Evaluation\Models\Competency::factory()->create();

        $this->actingAs($owner)
            ->post(route('workspace.internships.planning.store', [$workspace, $internship]), [
                'plan_date' => '2026-11-24',
                'slot' => 'morning',
                'title' => 'Lien invalide',
                'planned_minutes' => 210,
                'competency_ids' => [$foreign->id],
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('plan_entries', 0);
    }

    public function test_activity_store_links_plan_entries(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithOwner($owner);
        $trainee = User::factory()->create();
        [$internship, $project] = $this->internshipWithProject($workspace, $trainee);

        $entry = PlanEntry::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'plan_date' => '2026-11-24',
            'slot' => 'morning',
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.internships.projects.activities.store', [$workspace, $internship, $project]), [
                'title' => 'Activité liée au planning',
                'type' => 'feature',
                'status' => 'todo',
                'priority' => 'medium',
                'plan_entry_ids' => [$entry->id],
            ])
            ->assertRedirect();

        $activity = Activity::sole();
        $this->assertTrue($activity->planEntries->contains($entry->id));
        $this->assertDatabaseHas('plan_entry_activities', [
            'plan_entry_id' => $entry->id,
            'activity_id' => $activity->id,
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_planning_crud_rejects_user_without_manage_planning(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithOwner($owner);
        $trainee = User::factory()->create();
        [$internship] = $this->internshipWithProject($workspace, $trainee);

        $viewer = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $viewer->id,
            'role' => 'viewer',
        ]);
        // Le viewer est rattaché au stage pour voir le journal.
        InternshipParticipant::create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'user_id' => $viewer->id,
            'role' => 'observer',
            'status' => 'active',
        ]);

        $this->actingAs($viewer)
            ->post(route('workspace.internships.planning.store', [$workspace, $internship]), [
                'plan_date' => '2026-11-24',
                'slot' => 'morning',
                'title' => 'Non autorisé',
                'planned_minutes' => 60,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('plan_entries', 0);
    }

    public function test_planning_page_rejects_outsider(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithOwner($owner);
        $trainee = User::factory()->create();
        [$internship] = $this->internshipWithProject($workspace, $trainee);

        $outsider = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $outsider->id,
            'role' => 'viewer',
        ]);

        // viewer non rattaché au stage : pas de view_journal → 403.
        $this->actingAs($outsider)
            ->get(route('workspace.internships.planning.show', [$workspace, $internship]))
            ->assertForbidden();
    }
}
