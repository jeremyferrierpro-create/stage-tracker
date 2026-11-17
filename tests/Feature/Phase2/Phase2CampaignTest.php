<?php

namespace Tests\Feature\Phase2;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Journal\Models\DailyLog;
use App\Domain\Project\Models\Activity;
use App\Domain\Project\Models\Project;
use App\Domain\Project\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2CampaignTest extends TestCase
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

    public function test_task_status_persists_and_order_is_conserved(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);
        $trainee = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $trainee->id,
            'role' => 'trainee',
        ]);
        $internship = Internship::factory()->create([
            'workspace_id' => $workspace->id,
            'trainee_id' => $trainee->id,
            'company_name' => 'Entreprise Test',
        ]);
        $project = Project::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
        ]);
        $activity = Activity::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $workspace->id,
        ]);

        $taskA = Task::factory()->create([
            'activity_id' => $activity->id,
            'workspace_id' => $workspace->id,
            'title' => 'Tâche A',
            'position' => 1,
            'status' => 'todo',
        ]);
        $taskB = Task::factory()->create([
            'activity_id' => $activity->id,
            'workspace_id' => $workspace->id,
            'title' => 'Tâche B',
            'position' => 2,
            'status' => 'todo',
        ]);

        $this->actingAs($owner)
            ->put(route('workspace.tasks.update', [$workspace, $internship, $project, $activity, $taskA]), [
                'status' => 'done',
            ])
            ->assertRedirect();

        $taskA->refresh();
        $taskB->refresh();

        $this->assertSame('done', $taskA->status);
        $this->assertSame('todo', $taskB->status);
        $this->assertSame(1, $taskA->position);
        $this->assertSame(2, $taskB->position);
    }

    public function test_daily_log_closure_is_irreversible(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);
        $trainee = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $trainee->id,
            'role' => 'trainee',
        ]);
        $internship = Internship::factory()->create([
            'workspace_id' => $workspace->id,
            'trainee_id' => $trainee->id,
            'company_name' => 'Entreprise Test',
        ]);

        $log = DailyLog::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'author_id' => $owner->id,
            'work_date' => '2026-10-02',
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.internships.daily_logs.close', [$workspace, $internship, $log]))
            ->assertRedirect();

        $log->refresh();
        $this->assertTrue($log->isClosed());

        // Tentative de modification après clôture → 403.
        $this->actingAs($owner)
            ->put(route('workspace.internships.daily_logs.update', [$workspace, $internship, $log]), [
                'work_date' => '2026-10-03',
            ])
            ->assertForbidden();

        // Le statut reste closed, la date inchangée.
        $log->refresh();
        $this->assertTrue($log->isClosed());
        $this->assertSame('2026-10-02', $log->work_date->format('Y-m-d'));
    }

    public function test_internship_requires_a_company_name(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);
        $trainee = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $trainee->id,
            'role' => 'trainee',
        ]);

        // Un stage sans entreprise d'accueil est refusé (simplification 1.6.0).
        $this->actingAs($owner)
            ->post(route('workspace.internships.store', $workspace), [
                'title' => 'Stage Test',
                'trainee_id' => $trainee->id,
                'start_date' => '2026-09-01',
                'end_date' => '2027-01-31',
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('company_name');

        $this->assertSame(0, Internship::count());
    }
}
