<?php

namespace Tests\Feature\Planning;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Journal\Models\DailyLog;
use App\Domain\Planning\Models\PlanEntry;
use App\Domain\Planning\PlanningComparisonService;
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
