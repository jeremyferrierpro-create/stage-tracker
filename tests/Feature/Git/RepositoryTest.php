<?php

namespace Tests\Feature\Git;

use App\Domain\Git\Models\Commit;
use App\Domain\Git\Models\Repository;
use App\Domain\Git\Models\SyncRun;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Project\Models\Activity;
use App\Domain\Project\Models\Project;
use App\Integrations\Git\GitProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\FakeGitProvider;
use Tests\TestCase;

class RepositoryTest extends TestCase
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

    private function projectContext(User $user, string $role = 'owner'): array
    {
        $workspace = $this->workspaceWithMember($user, $role);
        $trainee = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $trainee->id,
            'role' => 'trainee',
        ]);
        $internship = Internship::factory()->create([
            'workspace_id' => $workspace->id,
            'trainee_id' => $trainee->id,
        ]);
        $project = Project::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
        ]);

        return [$workspace, $internship, $project];
    }

    public function test_token_is_never_exposed_in_responses_or_serialization(): void
    {
        [$workspace, $internship, $project] = $this->projectContext($owner = User::factory()->create());

        $this->actingAs($owner)
            ->post(route('workspace.internships.projects.repositories.store', [$workspace, $internship, $project]), [
                'provider' => 'github',
                'external_id' => 'demo/secret-repo',
                'name' => 'Dépôt avec jeton',
                'repository_url' => 'https://github.com/demo/secret-repo',
                'default_branch' => 'main',
                'token' => 'ghp_super_secret_token_1234567890',
            ])
            ->assertRedirect();

        $repository = Repository::query()->sole();

        // Chiffré en base, jamais en clair.
        $this->assertStringNotContainsString('ghp_super_secret_token_1234567890', (string) $repository->token_encrypted);

        // Absent de la sérialisation du modèle.
        $this->assertArrayNotHasKey('token_encrypted', $repository->toArray());

        // Absent de la page HTML du dépôt.
        $this->actingAs($owner)
            ->get(route('workspace.internships.projects.repositories.show', [$workspace, $internship, $project, $repository]))
            ->assertOk()
            ->assertDontSee('ghp_super_secret_token_1234567890');

        // Absent de la page projet.
        $this->actingAs($owner)
            ->get(route('workspace.internships.projects.show', [$workspace, $internship, $project]))
            ->assertOk()
            ->assertDontSee('ghp_super_secret_token_1234567890');
    }

    public function test_inaccessible_repository_sync_fails_with_clear_error(): void
    {
        $this->app->bind(GitProviderInterface::class, fn (): FakeGitProvider => new FakeGitProvider(
            throw: new RuntimeException('Dépôt inaccessible : vérifiez l\'URL et les droits du jeton.'),
        ));

        [$workspace, $internship, $project] = $this->projectContext($owner = User::factory()->create());
        $repository = Repository::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $workspace->id,
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.internships.projects.repositories.sync', [$workspace, $internship, $project, $repository]))
            ->assertRedirect()
            ->assertSessionHasErrors('sync');

        $run = SyncRun::query()->sole();

        $this->assertSame('failed', $run->status);
        $this->assertNotNull($run->error_summary);
        $this->assertSame(0, Commit::count());
    }

    public function test_test_connection_surfaces_a_clear_error_without_leaking_the_token(): void
    {
        $this->app->bind(GitProviderInterface::class, fn (): FakeGitProvider => new FakeGitProvider(
            throw: new RuntimeException("Accès refusé par GitHub : jeton invalide ou limite d'API atteinte."),
        ));

        [$workspace, $internship, $project] = $this->projectContext($owner = User::factory()->create());

        $this->actingAs($owner)
            ->post(route('workspace.internships.projects.repositories.test_connection', [$workspace, $internship, $project]), [
                'repository_url' => 'https://github.com/demo/forbidden-repo',
                'token' => 'ghp_bad_token',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('connection');

        $this->assertDatabaseCount('repositories', 0);
    }

    public function test_non_manager_member_cannot_manage_repositories(): void
    {
        [$workspace, $internship, $project] = $this->projectContext($owner = User::factory()->create());
        $supervisor = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $supervisor->id,
            'role' => 'supervisor',
        ]);

        $repository = Repository::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $workspace->id,
        ]);

        $this->actingAs($supervisor)
            ->post(route('workspace.internships.projects.repositories.store', [$workspace, $internship, $project]), [
                'provider' => 'github',
                'external_id' => 'demo/other',
                'name' => 'Sans droit',
                'repository_url' => 'https://github.com/demo/other',
            ])
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->post(route('workspace.internships.projects.repositories.sync', [$workspace, $internship, $project, $repository]))
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->delete(route('workspace.internships.projects.repositories.destroy', [$workspace, $internship, $project, $repository]))
            ->assertForbidden();
    }

    public function test_commit_linking_is_idempotent_and_multiple_commits_per_activity_allowed(): void
    {
        [$workspace, $internship, $project] = $this->projectContext($owner = User::factory()->create());
        $repository = Repository::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $workspace->id,
        ]);
        $activity = Activity::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $workspace->id,
        ]);
        $commitA = Commit::factory()->create([
            'repository_id' => $repository->id,
            'workspace_id' => $workspace->id,
        ]);
        $commitB = Commit::factory()->create([
            'repository_id' => $repository->id,
            'workspace_id' => $workspace->id,
        ]);

        $urlA = route('workspace.internships.projects.repositories.link_activity', [$workspace, $internship, $project, $repository, $commitA]);
        $urlB = route('workspace.internships.projects.repositories.link_activity', [$workspace, $internship, $project, $repository, $commitB]);

        $this->actingAs($owner)->post($urlA, ['activity_id' => $activity->id])->assertRedirect();
        $this->actingAs($owner)->post($urlA, ['activity_id' => $activity->id])->assertRedirect();

        $this->assertDatabaseCount('activity_commits', 1);

        // Plusieurs commits par activité (T-045).
        $this->actingAs($owner)
            ->post($urlB, ['activity_id' => $activity->id])
            ->assertRedirect();

        $this->assertDatabaseCount('activity_commits', 2);
        $this->assertDatabaseHas('activity_commits', ['activity_id' => $activity->id, 'commit_id' => $commitA->id]);
    }
}
