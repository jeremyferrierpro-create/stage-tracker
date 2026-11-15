<?php

namespace Tests\Feature\Evidence;

use App\Domain\Evidence\Models\Evidence;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Project\Models\Activity;
use App\Domain\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceTest extends TestCase
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

    public function test_upload_stores_file_outside_webroot_with_random_name(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);

        Storage::fake('local');

        $this->actingAs($owner)
            ->post(route('workspace.evidences.store', $workspace), [
                'title' => 'Capture du tableau de bord',
                'type' => 'screenshot',
                'demonstrated' => 'Le tableau de bord affiche les indicateurs.',
                'visibility' => 'shared',
                'file' => UploadedFile::fake()->create('tableau.png', 10, 'image/png'),
            ])
            ->assertRedirect();

        $evidence = Evidence::query()->sole();

        $this->assertNotNull($evidence->storage_key);
        $this->assertNull($evidence->external_url);
        $this->assertStringStartsWith('evidences/'.$workspace->id.'/'.$evidence->id.'/', $evidence->storage_key);
        $this->assertStringNotContainsString('tableau', $evidence->storage_key);

        Storage::disk('local')->assertExists($evidence->storage_key);
    }

    public function test_forbidden_file_type_is_rejected(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);

        $this->actingAs($owner)
            ->post(route('workspace.evidences.store', $workspace), [
                'title' => 'Exécutable',
                'type' => 'screenshot',
                'demonstrated' => 'Ne devrait pas passer.',
                'visibility' => 'private',
                'file' => UploadedFile::fake()->create('malware.exe', 10),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Evidence::count());
    }

    public function test_oversized_file_is_rejected(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);

        $this->actingAs($owner)
            ->post(route('workspace.evidences.store', $workspace), [
                'title' => 'Fichier trop gros',
                'type' => 'technical_doc',
                'demonstrated' => 'Ne devrait pas passer.',
                'visibility' => 'private',
                'file' => UploadedFile::fake()->create('gros.pdf', 10241),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Evidence::count());
    }

    public function test_mt07_private_evidence_is_inaccessible_to_supervisor(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);
        $trainee = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $trainee->id,
            'role' => 'trainee',
        ]);
        $supervisor = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $supervisor->id,
            'role' => 'supervisor',
        ]);

        $evidence = Evidence::factory()->create([
            'workspace_id' => $workspace->id,
            'owner_id' => $trainee->id,
            'visibility' => 'private',
        ]);

        // Non listée pour le maître de stage.
        $this->actingAs($supervisor)
            ->get(route('workspace.evidences.index', $workspace))
            ->assertOk()
            ->assertDontSee($evidence->title);

        // Détail refusé.
        $this->actingAs($supervisor)
            ->get(route('workspace.evidences.show', [$workspace, $evidence]))
            ->assertForbidden();

        // Téléchargement direct refusé.
        $this->actingAs($supervisor)
            ->get(route('workspace.evidences.download', [$workspace, $evidence]))
            ->assertForbidden();
    }

    public function test_mt08_shared_evidence_is_downloadable_by_authorized_member(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);
        $trainee = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $trainee->id,
            'role' => 'trainee',
        ]);

        $evidence = Evidence::factory()->shared()->create([
            'workspace_id' => $workspace->id,
            'owner_id' => $owner->id,
        ]);

        Storage::disk('local')->put($evidence->storage_key, 'contenu de la preuve');

        $this->actingAs($trainee)
            ->get(route('workspace.evidences.show', [$workspace, $evidence]))
            ->assertOk();

        $this->actingAs($trainee)
            ->get(route('workspace.evidences.download', [$workspace, $evidence]))
            ->assertOk();
    }

    public function test_one_evidence_can_document_several_activities_without_duplicates(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);
        $internship = Internship::factory()->create([
            'workspace_id' => $workspace->id,
        ]);
        $project = Project::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
        ]);
        $activityA = Activity::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $workspace->id,
        ]);
        $activityB = Activity::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $workspace->id,
        ]);

        $evidence = Evidence::factory()->create([
            'workspace_id' => $workspace->id,
            'owner_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.evidences.link_activity', [$workspace, $evidence]), [
                'activity_id' => $activityA->id,
                'note' => 'Illustre le tracé A.',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('workspace.evidences.link_activity', [$workspace, $evidence]), [
                'activity_id' => $activityB->id,
            ])
            ->assertRedirect();

        // Doublon (même paire) : idempotent, pas de seconde ligne.
        $this->actingAs($owner)
            ->post(route('workspace.evidences.link_activity', [$workspace, $evidence]), [
                'activity_id' => $activityA->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('activity_evidences', [
            'activity_id' => $activityA->id,
            'evidence_id' => $evidence->id,
            'note' => 'Illustre le tracé A.',
        ]);

        $this->assertDatabaseCount('activity_evidences', 2);
    }
}
