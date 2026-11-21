<?php

namespace Tests\Feature\Notes;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Notes\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceWith(User $user, string $role = 'owner'): Workspace
    {
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
        ]);

        return $workspace;
    }

    private function member(Workspace $workspace, string $role = 'viewer'): User
    {
        $user = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
        ]);

        return $user;
    }

    public function test_personal_note_is_invisible_to_other_members(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);
        $viewer = $this->member($workspace);

        $this->actingAs($owner)
            ->post(route('workspace.notes.store', $workspace), [
                'title' => 'Note secrète',
                'scope' => 'personal',
            ]);

        $this->actingAs($owner)
            ->get(route('workspace.notes.index', $workspace))
            ->assertOk()
            ->assertSee('Note secrète');

        $this->actingAs($viewer)
            ->get(route('workspace.notes.index', $workspace))
            ->assertOk()
            ->assertDontSee('Note secrète');
    }

    public function test_workspace_note_is_visible_to_all_members(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);
        $viewer = $this->member($workspace);

        $this->actingAs($owner)
            ->post(route('workspace.notes.store', $workspace), [
                'title' => 'Consigne commune',
                'scope' => 'workspace',
            ]);

        $this->actingAs($viewer)
            ->get(route('workspace.notes.index', $workspace))
            ->assertOk()
            ->assertSee('Consigne commune');
    }

    public function test_toggle_done_and_overdue(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);

        $this->actingAs($owner)
            ->post(route('workspace.notes.store', $workspace), [
                'title' => 'Tâche urgente',
                'scope' => 'personal',
                'due_at' => now()->subDay()->format('Y-m-d\TH:i'),
            ]);

        $note = Note::query()->firstOrFail();
        $this->assertTrue($note->isOverdue());

        $this->actingAs($owner)
            ->get(route('workspace.notes.index', $workspace).'?overdue=1')
            ->assertOk()
            ->assertSee('En retard');

        $this->actingAs($owner)
            ->post(route('workspace.notes.toggle', [$workspace, $note]));

        $this->assertSame('done', $note->fresh()->status);
        $this->assertFalse($note->fresh()->isOverdue());
    }

    public function test_reminder_creates_notification_once(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);

        Note::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'title' => 'Appeler le stagiaire',
            'scope' => 'personal',
            'status' => 'todo',
            'remind_at' => now()->subHour(),
        ]);

        // Premier chargement : notification créée.
        $this->actingAs($owner)->get(route('workspace.notes.index', $workspace))->assertOk();
        $this->assertDatabaseHas('notifications', [
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'type' => 'note_reminder',
        ]);

        // Second chargement : pas de doublon.
        $this->actingAs($owner)->get(route('workspace.notes.index', $workspace))->assertOk();
        $this->assertSame(1, \App\Domain\Notifications\Models\Notification::query()
            ->where('type', 'note_reminder')->count());
    }

    public function test_other_member_cannot_delete_someone_elses_shared_note(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);
        $viewer = $this->member($workspace);

        $this->actingAs($owner)
            ->post(route('workspace.notes.store', $workspace), [
                'title' => 'Note partagée',
                'scope' => 'workspace',
            ]);
        $note = Note::query()->firstOrFail();

        $this->actingAs($viewer)
            ->delete(route('workspace.notes.destroy', [$workspace, $note]))
            ->assertForbidden();

        $this->assertNotNull($note->fresh());
    }
}
