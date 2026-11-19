<?php

namespace Tests\Feature\Git;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Git\Models\Repository;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Project\Models\Project;
use App\Integrations\Git\GitProviderInterface;
use App\Jobs\SyncRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeGitProvider;
use Tests\TestCase;

class SyncRepositoryJobTest extends TestCase
{
    use RefreshDatabase;

    private function repositoryContext(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
        $internship = Internship::factory()->create(['workspace_id' => $workspace->id]);
        $project = Project::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
        ]);
        $repository = Repository::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $workspace->id,
            'name' => 'Mon Repo',
        ]);

        return [$owner, $workspace, $internship, $project, $repository];
    }

    public function test_sync_repository_job_runs_import_and_records_audit_log(): void
    {
        [$owner, $workspace, $internship, $project, $repository] = $this->repositoryContext();

        $this->app->bind(GitProviderInterface::class, fn (): FakeGitProvider => new FakeGitProvider([
            [
                'sha' => str_repeat('e', 40),
                'message' => 'feat: async sync',
                'author_name' => 'Dev',
                'author_email' => 'dev@example.test',
                'committed_at' => '2026-11-19T10:00:00+00:00',
                'url' => 'https://github.com/demo/repo/commit/'.str_repeat('e', 40),
                'branch' => 'main',
                'files' => [
                    ['path' => 'file1.txt', 'change_type' => 'added', 'additions' => 10, 'deletions' => 0],
                ],
            ],
        ]));

        $job = new SyncRepository($repository->id, $owner->id);
        app()->call([$job, 'handle']);

        $this->assertDatabaseHas('sync_runs', [
            'repository_id' => $repository->id,
            'status' => 'success',
            'items_imported' => 1,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'repository.synced',
            'workspace_id' => $workspace->id,
            'actor_id' => $owner->id,
        ]);

        $log = AuditLog::query()->where('action', 'repository.synced')->firstOrFail();
        $this->assertSame('Mon Repo', $log->metadata['repository_name'] ?? null);
        $this->assertSame('success', $log->metadata['status'] ?? null);
        $this->assertSame(1, $log->metadata['items_imported'] ?? null);
    }

    public function test_sync_endpoint_dispatches_job_when_queue_is_not_sync(): void
    {
        Queue::fake();
        config(['queue.default' => 'database']);

        [$owner, $workspace, $internship, $project, $repository] = $this->repositoryContext();

        $response = $this->actingAs($owner)->post(
            route('workspace.internships.projects.repositories.sync', [$workspace, $internship, $project, $repository])
        );

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Synchronisation lancée en arrière-plan.');

        Queue::assertPushed(SyncRepository::class, function ($job) use ($repository, $owner) {
            return $job->repositoryId === $repository->getKey() && $job->userId === $owner->getKey();
        });
    }
}
