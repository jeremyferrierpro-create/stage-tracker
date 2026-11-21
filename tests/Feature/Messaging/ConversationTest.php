<?php

namespace Tests\Feature\Messaging;

use App\Domain\Collaboration\Models\Conversation;
use App\Domain\Collaboration\Models\ConversationParticipant;
use App\Domain\Collaboration\Models\Message;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConversationTest extends TestCase
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

    public function test_direct_conversation_send_and_unread(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);
        $viewer = $this->member($workspace);

        $this->actingAs($owner)
            ->post(route('workspace.messages.store', $workspace), ['type' => 'direct', 'user_id' => $viewer->id])
            ->assertRedirect();

        $conversation = Conversation::query()->where('workspace_id', $workspace->id)->firstOrFail();
        $this->assertSame('direct', $conversation->type);

        $this->actingAs($owner)
            ->post(route('workspace.messages.messages.store', [$workspace, $conversation]), ['body' => 'Bonjour']);

        $this->assertSame(1, Message::query()->count());

        // Le destinataire voit 1 message non lu ; l'expéditeur, zéro.
        $this->actingAs($viewer)
            ->get(route('workspace.messages.index', $workspace))
            ->assertOk()
            ->assertSee('1 non lu');

        $this->actingAs($viewer)
            ->get(route('workspace.messages.show', [$workspace, $conversation]))
            ->assertOk()
            ->assertSee('Bonjour');

        // Après lecture, plus de non lus.
        $this->actingAs($viewer)
            ->get(route('workspace.messages.index', $workspace))
            ->assertDontSee('non lu(s)');
    }

    public function test_non_participant_cannot_read_direct_conversation(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);
        $outsider = $this->member($workspace);
        $other = $this->member($workspace);

        $this->actingAs($owner)
            ->post(route('workspace.messages.store', $workspace), ['type' => 'direct', 'user_id' => $outsider->id]);
        $conversation = Conversation::query()->firstOrFail();

        // Membre de l'espace mais pas de la conversation directe → 403.
        $this->actingAs($other)
            ->get(route('workspace.messages.show', [$workspace, $conversation]))
            ->assertForbidden();
    }

    public function test_viewer_can_read_and_post_messages(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);
        $viewer = $this->member($workspace, 'viewer');

        $this->actingAs($viewer)
            ->post(route('workspace.messages.store', $workspace), ['type' => 'direct', 'user_id' => $owner->id]);
        $conversation = Conversation::query()->firstOrFail();

        $this->actingAs($viewer)
            ->post(route('workspace.messages.messages.store', [$workspace, $conversation]), ['body' => 'Vu'])
            ->assertRedirect();

        $this->assertSame('Vu', Message::query()->firstOrFail()->body);
    }

    public function test_attachment_upload_and_download(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);
        $viewer = $this->member($workspace);

        $this->actingAs($owner)
            ->post(route('workspace.messages.store', $workspace), ['type' => 'direct', 'user_id' => $viewer->id]);
        $conversation = Conversation::query()->firstOrFail();

        $file = UploadedFile::fake()->create('note.txt', 10, 'text/plain');

        $this->actingAs($owner)
            ->post(route('workspace.messages.messages.store', [$workspace, $conversation]), [
                'body' => 'Voici le fichier',
                'attachment' => $file,
            ])
            ->assertRedirect();

        $message = Message::query()->firstOrFail();
        $this->assertNotNull($message->attachment_path);

        $this->actingAs($viewer)
            ->get(route('workspace.messages.attachments.download', [$workspace, $conversation, $message]))
            ->assertOk();
    }

    public function test_invalid_attachment_is_rejected(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);
        $viewer = $this->member($workspace);

        $this->actingAs($owner)
            ->post(route('workspace.messages.store', $workspace), ['type' => 'direct', 'user_id' => $viewer->id]);
        $conversation = Conversation::query()->firstOrFail();

        $file = UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload');

        $this->actingAs($owner)
            ->post(route('workspace.messages.messages.store', [$workspace, $conversation]), [
                'attachment' => $file,
            ])
            ->assertSessionHasErrors('attachment');
    }

    public function test_message_notifies_other_participants(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);
        $viewer = $this->member($workspace);

        $this->actingAs($owner)
            ->post(route('workspace.messages.store', $workspace), ['type' => 'direct', 'user_id' => $viewer->id]);
        $conversation = Conversation::query()->firstOrFail();

        $this->actingAs($owner)
            ->post(route('workspace.messages.messages.store', [$workspace, $conversation]), ['body' => 'Salut']);

        $this->assertDatabaseHas('notifications', [
            'workspace_id' => $workspace->id,
            'user_id' => $viewer->id,
            'type' => 'message',
        ]);
    }
}
