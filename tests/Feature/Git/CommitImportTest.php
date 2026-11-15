<?php

namespace Tests\Feature\Git;

use App\Domain\Git\Models\ActivityCommit;
use App\Domain\Git\Models\Commit;
use App\Domain\Git\Models\CommitFile;
use App\Domain\Git\Models\Repository;
use App\Domain\Git\Models\SyncRun;
use App\Domain\Git\Services\CommitImportService;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Project\Models\Activity;
use App\Domain\Project\Models\Project;
use App\Integrations\Git\GitProviderInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\FakeGitProvider;
use Tests\TestCase;
use Throwable;

class CommitImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fournisseur factice : retourne toujours le même lot de commits,
     * comme si deux synchronisations hitaient la même page d'API.
     *
     * @param  list<array<string, mixed>>  $commits
     */
    private function bindFakeProvider(array $commits, ?Throwable $throw = null): void
    {
        $this->app->bind(GitProviderInterface::class, fn (): FakeGitProvider => new FakeGitProvider($commits, $throw));
    }

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
        ]);

        return [$owner, $workspace, $internship, $project, $repository];
    }

    public function test_mt06_double_import_creates_no_duplicates_and_traces_both_runs(): void
    {
        [$owner, $workspace, $internship, $project, $repository] = $this->repositoryContext();

        $activity = Activity::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $workspace->id,
        ]);

        $this->bindFakeProvider([
            [
                'sha' => str_repeat('a', 40),
                'message' => 'feat: maquette',
                'author_name' => 'Stagiaire',
                'author_email' => 'stagiaire@example.test',
                'committed_at' => '2026-11-01T10:00:00+00:00',
                'url' => 'https://github.com/demo/repo/commit/'.str_repeat('a', 40),
                'branch' => 'main',
                'files' => [
                    ['path' => 'README.md', 'change_type' => 'modified', 'additions' => 12, 'deletions' => 3],
                    ['path' => 'resources/views/home.blade.php', 'change_type' => 'added', 'additions' => 80, 'deletions' => 0],
                ],
            ],
            [
                'sha' => str_repeat('b', 40),
                'message' => 'fix: correctif',
                'author_name' => 'Stagiaire',
                'author_email' => 'stagiaire@example.test',
                'committed_at' => '2026-11-02T10:00:00+00:00',
                'url' => null,
                'branch' => 'main',
                'files' => [],
            ],
        ]);

        $service = $this->app->make(CommitImportService::class);

        $run1 = $service->sync($repository);
        $this->assertSame('success', $run1->status);
        $this->assertSame(2, $run1->items_imported);
        $this->assertSame(2, Commit::count());
        $this->assertSame(2, CommitFile::count());

        $commit = Commit::query()->where('sha', str_repeat('a', 40))->sole();
        $importedAt = $commit->imported_at;

        // Association commit ↔ activité avant la seconde synchronisation.
        ActivityCommit::create([
            'activity_id' => $activity->id,
            'commit_id' => $commit->id,
            'workspace_id' => $workspace->id,
            'linked_by' => $owner->id,
            'linked_at' => now(),
        ]);

        // Seconde synchronisation : même page d'API.
        $run2 = $service->sync($repository);

        $this->assertSame('success', $run2->status);
        $this->assertSame(0, $run2->items_imported);
        $this->assertSame(2, Commit::count());
        $this->assertSame(2, CommitFile::count());
        $this->assertSame(2, SyncRun::query()->count());

        // Provenance conservée : imported_at inchangé, association intacte.
        $commit->refresh();
        $this->assertTrue($commit->imported_at->equalTo($importedAt));
        $this->assertDatabaseHas('activity_commits', [
            'activity_id' => $activity->id,
            'commit_id' => $commit->id,
        ]);
    }

    public function test_commit_files_are_updated_without_duplicates_on_resync(): void
    {
        [$owner, $workspace, $internship, $project, $repository] = $this->repositoryContext();

        $sha = str_repeat('c', 40);
        $filesV1 = [
            ['path' => 'app/Models/Demo.php', 'change_type' => 'added', 'additions' => 10, 'deletions' => 0],
        ];
        $filesV2 = [
            ['path' => 'app/Models/Demo.php', 'change_type' => 'modified', 'additions' => 25, 'deletions' => 8],
            ['path' => 'app/Models/Other.php', 'change_type' => 'added', 'additions' => 5, 'deletions' => 0],
        ];

        $this->bindFakeProvider([
            ['sha' => $sha, 'message' => 'ajout', 'author_name' => null, 'author_email' => null,
                'committed_at' => '2026-11-03T10:00:00+00:00', 'url' => null, 'branch' => 'main', 'files' => $filesV1],
        ]);
        $this->app->make(CommitImportService::class)->sync($repository);
        $this->assertSame(1, CommitFile::count());

        // Re-import avec fichiers modifiés : upsert sans doublon.
        $this->bindFakeProvider([
            ['sha' => $sha, 'message' => 'modification', 'author_name' => null, 'author_email' => null,
                'committed_at' => '2026-11-03T10:00:00+00:00', 'url' => null, 'branch' => 'main', 'files' => $filesV2],
        ]);
        $this->app->make(CommitImportService::class)->sync($repository);

        $this->assertSame(2, CommitFile::count());
        $this->assertDatabaseHas('commit_files', [
            'path' => 'app/Models/Demo.php',
            'change_type' => 'modified',
            'additions' => 25,
            'deletions' => 8,
        ]);
    }

    public function test_partial_failure_is_traced_and_resumable(): void
    {
        [$owner, $workspace, $internship, $project, $repository] = $this->repositoryContext();

        // Première exécution : échec (limite d'API), zéro commit importé.
        $this->bindFakeProvider([], throw: new RuntimeException("Accès refusé par GitHub : jeton invalide ou limite d'API atteinte."));
        $run = $this->app->make(CommitImportService::class)->sync($repository);

        $this->assertSame('failed', $run->status);
        $this->assertSame(0, $run->items_imported);
        $this->assertStringContainsString("limite d'API", (string) $run->error_summary);
        $this->assertSame(0, Commit::count());

        // Reprise : le fournisseur répond, l'import réussit.
        $this->bindFakeProvider([
            ['sha' => str_repeat('d', 40), 'message' => 'reprise', 'author_name' => null, 'author_email' => null,
                'committed_at' => '2026-11-04T10:00:00+00:00', 'url' => null, 'branch' => 'main', 'files' => []],
        ]);
        $run2 = $this->app->make(CommitImportService::class)->sync($repository);

        $this->assertSame('success', $run2->status);
        $this->assertSame(1, $run2->items_imported);
        $this->assertSame(2, SyncRun::query()->count());
    }

    public function test_isolation_commits_belong_to_their_workspace_only(): void
    {
        [$owner, $workspace, $internship, $project, $repository] = $this->repositoryContext();
        $otherOwner = User::factory()->create();
        $otherWorkspace = Workspace::factory()->create(['owner_id' => $otherOwner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'user_id' => $otherOwner->id,
            'role' => 'owner',
        ]);

        $otherInternship = Internship::factory()->create(['workspace_id' => $otherWorkspace->id]);
        $otherProject = Project::factory()->create([
            'internship_id' => $otherInternship->id,
            'workspace_id' => $otherWorkspace->id,
        ]);
        $otherActivity = Activity::factory()->create([
            'project_id' => $otherProject->id,
            'workspace_id' => $otherWorkspace->id,
        ]);

        $commit = Commit::factory()->create([
            'repository_id' => $repository->id,
            'workspace_id' => $workspace->id,
        ]);

        // Un commit d'un autre workspace ne peut pas être lié à une activité
        // de l'espace voisin : la FK composite (commit_id, workspace_id)
        // interdit l'association inter-espaces.
        $this->expectException(QueryException::class);

        ActivityCommit::create([
            'activity_id' => $otherActivity->id,
            'commit_id' => $commit->id,
            'workspace_id' => $otherWorkspace->id,
            'linked_by' => $otherOwner->id,
            'linked_at' => now(),
        ]);
    }
}
