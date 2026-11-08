<?php

namespace Tests\Feature\Identity;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Mail\WorkspaceInvitationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceWith(User $user, string $role = 'owner'): Workspace
    {
        $owner = $role === 'owner' ? $user : User::factory()->create();

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
        ]);

        return $workspace;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: Invitation, 1: string}
     */
    private function invitationFor(Workspace $workspace, User $inviter, array $overrides = []): array
    {
        $token = Str::random(48);

        $invitation = Invitation::factory()->create(array_merge([
            'workspace_id' => $workspace->id,
            'email' => 'invite@example.test',
            'role' => 'trainee',
            'token_hash' => Invitation::hashToken($token),
            'expires_at' => now()->addDays(7),
            'created_by' => $inviter->id,
        ], $overrides));

        return [$invitation, $token];
    }

    public function test_owner_can_invite_a_participant_and_only_the_token_hash_is_stored(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);

        $this->actingAs($owner)
            ->from(route('workspace.members.index', $workspace))
            ->post(route('workspace.invitations.store', $workspace), [
                'email' => 'Invite@Example.TEST',
                'role' => 'supervisor',
            ])
            ->assertRedirect(route('workspace.members.index', $workspace))
            ->assertSessionHas('status');

        $invitation = Invitation::firstOrFail();
        $token = null;

        Mail::assertSent(WorkspaceInvitationMail::class, function (WorkspaceInvitationMail $mail) use (&$token): bool {
            $token = $mail->token;

            return $mail->hasTo('invite@example.test');
        });

        $this->assertSame('invite@example.test', $invitation->email);
        $this->assertSame('supervisor', $invitation->role);
        $this->assertSame(Invitation::hashToken($token), $invitation->token_hash);
        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertDatabaseMissing('invitations', ['token_hash' => $token]);
        $this->assertTrue($invitation->expires_at->between(
            now()->addDays(7)->subMinute(),
            now()->addDays(7)->addMinute(),
        ));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'invitation.created',
            'workspace_id' => $workspace->id,
            'actor_id' => $owner->id,
        ]);
    }

    public function test_owner_can_invite_an_admin_but_admin_cannot_invite_an_admin(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);

        $this->actingAs($owner)
            ->post(route('workspace.invitations.store', $workspace), [
                'email' => 'admin-invite@example.test',
                'role' => 'admin',
            ])
            ->assertSessionHasNoErrors();

        $admin = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(route('workspace.invitations.store', $workspace), [
                'email' => 'second-admin@example.test',
                'role' => 'admin',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('invitations', ['email' => 'second-admin@example.test']);
    }

    public function test_owner_role_cannot_be_proposed(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);

        $this->actingAs($owner)
            ->post(route('workspace.invitations.store', $workspace), [
                'email' => 'owner-bis@example.test',
                'role' => 'owner',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('invitations', ['email' => 'owner-bis@example.test']);
    }

    public function test_trainee_cannot_manage_invitations(): void
    {
        $trainee = User::factory()->create();
        $workspace = $this->workspaceWith($trainee, 'trainee');

        $this->actingAs($trainee)
            ->get(route('workspace.members.index', $workspace))
            ->assertForbidden();

        $this->actingAs($trainee)
            ->post(route('workspace.invitations.store', $workspace), [
                'email' => 'someone@example.test',
                'role' => 'trainee',
            ])
            ->assertForbidden();
    }

    public function test_duplicate_pending_invitation_is_rejected(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);
        $this->invitationFor($workspace, $owner, ['email' => 'pending@example.test']);

        $this->actingAs($owner)
            ->post(route('workspace.invitations.store', $workspace), [
                'email' => 'pending@example.test',
                'role' => 'trainee',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Invitation::where('email', 'pending@example.test')->count());
    }

    public function test_invitation_page_shows_valid_invitation_and_rejects_invalid_expired_or_used_tokens(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);

        [$valid, $validToken] = $this->invitationFor($workspace, $owner, ['email' => 'valid@example.test']);
        $this->get(route('invitations.show', $validToken))
            ->assertOk()
            ->assertSee($workspace->name)
            ->assertSee('valid@example.test');

        $this->get(route('invitations.show', Str::random(48)))->assertNotFound();

        [$expired, $expiredToken] = $this->invitationFor($workspace, $owner, [
            'email' => 'expired@example.test',
            'expires_at' => now()->subDay(),
        ]);
        $this->get(route('invitations.show', $expiredToken))->assertStatus(410);

        [$used, $usedToken] = $this->invitationFor($workspace, $owner, [
            'email' => 'used@example.test',
            'accepted_at' => now(),
        ]);
        $this->get(route('invitations.show', $usedToken))->assertStatus(410);
    }

    public function test_invitee_can_accept_with_exactly_the_proposed_role(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);
        $invitee = User::factory()->create(['email' => 'invitee@example.test']);

        [, $token] = $this->invitationFor($workspace, $owner, [
            'email' => 'invitee@example.test',
            'role' => 'supervisor',
        ]);

        $this->actingAs($invitee)
            ->post(route('invitations.accept', $token))
            ->assertRedirect(route('workspace.dashboard', $workspace));

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $invitee->id,
            'role' => 'supervisor',
            'status' => 'active',
        ]);

        $this->assertNotNull(Invitation::firstOrFail()->accepted_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'invitation.accepted',
            'workspace_id' => $workspace->id,
            'actor_id' => $invitee->id,
        ]);

        // Double acceptation : le token n'est plus utilisable.
        $this->actingAs($invitee)
            ->post(route('invitations.accept', $token))
            ->assertStatus(410);
    }

    public function test_wrong_account_cannot_accept_invitation(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);
        $other = User::factory()->create(['email' => 'other@example.test']);

        [, $token] = $this->invitationFor($workspace, $owner, [
            'email' => 'invitee@example.test',
        ]);

        $this->actingAs($other)
            ->post(route('invitations.accept', $token))
            ->assertForbidden();

        $this->assertDatabaseMissing('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $other->id,
        ]);
    }

    public function test_guest_is_redirected_to_login_before_accepting(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);

        [, $token] = $this->invitationFor($workspace, $owner);

        $this->post(route('invitations.accept', $token))
            ->assertRedirect(route('login'));
    }

    public function test_invitee_can_decline_and_the_event_remains_audited(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);
        $invitee = User::factory()->create(['email' => 'decline@example.test']);

        [$invitation, $token] = $this->invitationFor($workspace, $owner, [
            'email' => 'decline@example.test',
        ]);

        $this->actingAs($invitee)
            ->post(route('invitations.decline', $token))
            ->assertRedirect(route('home'));

        $this->assertDatabaseMissing('invitations', ['id' => $invitation->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'invitation.declined',
            'workspace_id' => $workspace->id,
            'actor_id' => $invitee->id,
        ]);
    }

    public function test_administrator_can_cancel_a_pending_invitation(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);

        [$invitation] = $this->invitationFor($workspace, $owner, [
            'email' => 'cancel@example.test',
        ]);

        $this->actingAs($owner)
            ->from(route('workspace.members.index', $workspace))
            ->delete(route('workspace.invitations.destroy', [$workspace, $invitation]))
            ->assertRedirect(route('workspace.members.index', $workspace));

        $this->assertDatabaseMissing('invitations', ['id' => $invitation->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'invitation.cancelled',
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_registration_via_invitation_creates_the_account_and_membership(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);

        [, $token] = $this->invitationFor($workspace, $owner, [
            'email' => 'newcomer@example.test',
            'role' => 'tutor',
        ]);

        $this->post(route('invitations.register', $token), [
            'name' => 'Nouveau Membre',
            'password' => 'mot-de-passe-solide',
            'password_confirmation' => 'mot-de-passe-solide',
        ])->assertRedirect(route('workspace.dashboard', $workspace));

        $user = User::where('email', 'newcomer@example.test')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'tutor',
            'status' => 'active',
        ]);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull(Invitation::firstOrFail()->accepted_at);
    }

    public function test_registration_is_refused_when_an_account_already_exists(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);
        $existing = User::factory()->create(['email' => 'existing@example.test']);

        [, $token] = $this->invitationFor($workspace, $owner, [
            'email' => 'existing@example.test',
        ]);

        $this->post(route('invitations.register', $token), [
            'name' => 'Doublon',
            'password' => 'mot-de-passe-solide',
            'password_confirmation' => 'mot-de-passe-solide',
        ])->assertRedirect(route('login', ['redirect' => route('invitations.show', $token)]))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'existing@example.test')->count());
        $this->assertGuest();
        $this->assertNotNull($existing->fresh());
    }

    public function test_invitation_creation_is_audited_without_plain_email(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);

        $this->actingAs($owner)
            ->post(route('workspace.invitations.store', $workspace), [
                'email' => 'privacy@example.test',
                'role' => 'trainee',
            ]);

        $log = AuditLog::where('action', 'invitation.created')->firstOrFail();

        $this->assertSame($workspace->id, $log->workspace_id);
        $this->assertSame($owner->id, $log->actor_id);
        $this->assertArrayHasKey('email_hash', $log->metadata);
        $this->assertArrayNotHasKey('email', $log->metadata);
        $this->assertStringNotContainsString('privacy@example.test', json_encode($log->metadata));
    }
}
