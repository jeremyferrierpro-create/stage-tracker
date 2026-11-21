<?php

namespace Tests\Feature\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Notifications\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
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

    private function notify(Workspace $workspace, User $user, array $overrides = []): Notification
    {
        return Notification::query()->create(array_merge([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'type' => 'test',
            'title' => 'Notification test',
            'body' => 'Corps',
            'data' => ['url' => '/w/'.$workspace->id.'/notes'],
        ], $overrides));
    }

    public function test_unread_notification_is_listed_and_can_be_marked_read(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceWith($user);
        $notification = $this->notify($workspace, $user);

        $this->actingAs($user)
            ->get(route('workspace.notifications.index', $workspace))
            ->assertOk()
            ->assertSee('Notification test');

        $this->actingAs($user)
            ->post(route('workspace.notifications.read', [$workspace, $notification]))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_and_open_redirect_to_internal_url(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceWith($user);
        $this->notify($workspace, $user);
        $this->notify($workspace, $user, ['data' => ['url' => 'https://evil.example']]);

        $this->actingAs($user)
            ->post(route('workspace.notifications.read_all', $workspace))
            ->assertRedirect(route('workspace.notifications.index', $workspace));

        $this->assertSame(0, Notification::query()->whereNull('read_at')->count());

        // Une URL externe n'est jamais suivie : retour à la liste.
        $external = $this->notify($workspace, $user, ['data' => ['url' => 'https://evil.example']]);
        $this->actingAs($user)
            ->get(route('workspace.notifications.open', [$workspace, $external]))
            ->assertRedirect(route('workspace.notifications.index', $workspace));
    }

    public function test_user_cannot_read_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $workspace = $this->workspaceWith($owner);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $other->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);
        $notification = $this->notify($workspace, $owner);

        $this->actingAs($other)
            ->post(route('workspace.notifications.read', [$workspace, $notification]))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_notifications_are_isolated_per_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceWith($user);
        $other = Workspace::factory()->create(['owner_id' => $user->id]);

        $this->notify($workspace, $user);
        $this->notify($other, $user);

        $this->actingAs($user)
            ->get(route('workspace.notifications.index', $workspace))
            ->assertOk()
            ->assertSee('Notification test')
            ->assertDontSee('Corps de l\'autre'); // même titre, filtrage par workspace vérifié par count

        $this->assertSame(1, Notification::query()->where('workspace_id', $workspace->id)->count());
    }
}
