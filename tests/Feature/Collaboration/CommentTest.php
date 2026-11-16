<?php

namespace Tests\Feature\Collaboration;

use App\Domain\Authorization\EffectivePermissions;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Internship\Models\InternshipParticipant;
use App\Domain\Project\Models\Activity;
use App\Domain\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    private function context(User $owner): array
    {
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

        $internship = Internship::factory()->create([
            'workspace_id' => $workspace->id,
        ]);
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
        ]);

        return [$workspace, $internship, $project, $activity, $supervisor];
    }

    public function test_mt09_author_date_and_content_are_immutable(): void
    {
        [$workspace, $internship, $project, $activity, $supervisor] = $this->context($owner = User::factory()->create());

        $this->actingAs($supervisor)
            ->post(route('workspace.internships.projects.activities.comments.store', [$workspace, $internship, $project, $activity]), [
                'content' => 'Bon avancement sur la maquette.',
                'visibility' => 'stage',
            ])
            ->assertRedirect();

        $comment = Comment::query()->sole();
        $authorId = $comment->author_id;
        $createdAt = $comment->created_at;
        $content = $comment->content;

        // D'autres données évoluent (statut d'activité, nouveau commentaire).
        $activity->forceFill(['status' => 'done'])->save();
        $this->actingAs($owner)
            ->post(route('workspace.internships.projects.activities.comments.store', [$workspace, $internship, $project, $activity]), [
                'content' => 'Merci pour le retour.',
                'visibility' => 'shared',
            ])
            ->assertRedirect();

        // Le commentaire d'origine est inchangé (INV-12).
        $comment->refresh();
        $this->assertSame($authorId, $comment->author_id);
        $this->assertTrue($comment->created_at->equalTo($createdAt));
        $this->assertSame($content, $comment->content);
        $this->assertSame(2, Comment::query()->count());
    }

    public function test_xss_content_is_escaped_in_html(): void
    {
        [$workspace, $internship, $project, $activity] = $this->context($owner = User::factory()->create());

        $this->actingAs($owner)
            ->post(route('workspace.internships.projects.activities.comments.store', [$workspace, $internship, $project, $activity]), [
                'content' => "<script>alert('xss')</script>",
                'visibility' => 'shared',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->get(route('workspace.internships.projects.activities.show', [$workspace, $internship, $project, $activity]))
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert', false);
    }

    public function test_private_comment_is_visible_to_author_only(): void
    {
        [$workspace, $internship, $project, $activity, $supervisor] = $this->context($owner = User::factory()->create());

        $this->actingAs($owner)
            ->post(route('workspace.internships.projects.activities.comments.store', [$workspace, $internship, $project, $activity]), [
                'content' => 'Note privée du propriétaire.',
                'visibility' => 'private',
            ])
            ->assertRedirect();

        // L'auteur le voit sur la page.
        $this->actingAs($owner)
            ->get(route('workspace.internships.projects.activities.show', [$workspace, $internship, $project, $activity]))
            ->assertOk()
            ->assertSee('Note privée du propriétaire.');

        // Le maître de stage (participant) ne le voit ni sur la page, ni via un modèle.
        $this->actingAs($supervisor)
            ->get(route('workspace.internships.projects.activities.show', [$workspace, $internship, $project, $activity]))
            ->assertOk()
            ->assertDontSee('Note privée du propriétaire.');

        $visible = Comment::query()
            ->where('activity_id', $activity->id)
            ->get()
            ->filter(fn (Comment $c) => $c->visibility !== 'private' || $c->author_id === $supervisor->id);

        $this->assertSame(0, $visible->count());
    }

    public function test_supervisor_can_comment_but_not_create_activities(): void
    {
        [$workspace, $internship, $project, $activity, $supervisor] = $this->context($owner = User::factory()->create());

        $permissions = EffectivePermissions::resolveForInternship($supervisor, $workspace, $internship);

        $this->assertTrue($permissions['comment']);
        $this->assertFalse($permissions['create_activity']);
        $this->assertFalse($permissions['create_journal']);
        $this->assertTrue($permissions['review']);
    }

    public function test_viewer_can_comment_with_shared_visibility_only(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        $viewer = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $viewer->id,
            'role' => 'viewer',
        ]);

        $internship = Internship::factory()->create(['workspace_id' => $workspace->id]);
        InternshipParticipant::query()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'user_id' => $viewer->id,
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
        ]);

        // Un viewer tente un commentaire privé : interdit (🔶 partagé seulement).
        $this->actingAs($viewer)
            ->post(route('workspace.internships.projects.activities.comments.store', [$workspace, $internship, $project, $activity]), [
                'content' => 'Tentative privée.',
                'visibility' => 'private',
            ])
            ->assertSessionHasErrors('visibility');

        $this->actingAs($viewer)
            ->post(route('workspace.internships.projects.activities.comments.store', [$workspace, $internship, $project, $activity]), [
                'content' => 'Commentaire partagé du lecteur.',
                'visibility' => 'shared',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'content' => 'Commentaire partagé du lecteur.',
            'visibility' => 'shared',
        ]);
    }

    public function test_non_member_cannot_comment(): void
    {
        [$workspace, $internship, $project, $activity] = $this->context(User::factory()->create());

        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('workspace.internships.projects.activities.comments.store', [$workspace, $internship, $project, $activity]), [
                'content' => 'Je ne suis pas membre.',
                'visibility' => 'shared',
            ])
            ->assertForbidden();

        $this->assertSame(0, Comment::query()->count());
    }

    public function test_author_and_manager_can_delete_but_not_other_participants(): void
    {
        [$workspace, $internship, $project, $activity, $supervisor] = $this->context($owner = User::factory()->create());

        $this->actingAs($supervisor)
            ->post(route('workspace.internships.projects.activities.comments.store', [$workspace, $internship, $project, $activity]), [
                'content' => 'À supprimer.',
                'visibility' => 'stage',
            ])
            ->assertRedirect();

        $comment = Comment::query()->sole();

        // Le propriétaire (auteur) supprime son propre commentaire.
        $this->actingAs($owner)
            ->delete(route('workspace.internships.projects.activities.comments.destroy', [$workspace, $internship, $project, $activity, $comment]))
            ->assertRedirect();

        $this->assertSame(0, Comment::query()->count());

        // Un maître de stage ne peut pas supprimer le commentaire d'un autre.
        $this->actingAs($owner)
            ->post(route('workspace.internships.projects.activities.comments.store', [$workspace, $internship, $project, $activity]), [
                'content' => 'D\'un autre.',
                'visibility' => 'shared',
            ])
            ->assertRedirect();

        $other = Comment::query()->sole();

        $this->actingAs($supervisor)
            ->delete(route('workspace.internships.projects.activities.comments.destroy', [$workspace, $internship, $project, $activity, $other]))
            ->assertForbidden();

        $this->assertSame(1, Comment::query()->count());
    }
}
