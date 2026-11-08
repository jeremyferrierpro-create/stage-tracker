<?php

namespace Tests\Feature\Audit;

use App\Domain\Audit\AuditLogger;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
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

    public function test_successful_login_creates_an_audit_entry(): void
    {
        $user = User::factory()->create([
            'password_hash' => 'correct-horse-battery',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('workspaces.index'));

        $log = AuditLog::where('action', 'auth.login')->firstOrFail();

        $this->assertSame($user->id, $log->actor_id);
        $this->assertNull($log->workspace_id);
        $this->assertSame(User::class, $log->subject_type);
        $this->assertSame($user->id, (int) $log->subject_id);
        $this->assertSame('success', $log->metadata['status']);
        $this->assertNotNull($log->created_at);
    }

    public function test_failed_login_is_audited_without_the_password(): void
    {
        $user = User::factory()->create([
            'password_hash' => 'correct-horse-battery',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $log = AuditLog::where('action', 'auth.login.failed')->firstOrFail();

        $this->assertSame($user->id, $log->actor_id);
        $this->assertArrayHasKey('email_hash', $log->metadata);
        $this->assertArrayNotHasKey('password', $log->metadata);
        $this->assertStringNotContainsString('wrong-password', json_encode($log->metadata));
    }

    public function test_throttled_login_is_audited(): void
    {
        $user = User::factory()->create([
            'password_hash' => 'correct-horse-battery',
        ]);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login.throttled']);
    }

    public function test_audit_entry_cannot_be_updated_through_the_model(): void
    {
        $log = app(AuditLogger::class)->record('test.event', null, null, ['role' => 'trainee']);

        $this->expectException(LogicException::class);

        $log->update(['action' => 'tampered']);
    }

    public function test_database_trigger_rejects_audit_updates(): void
    {
        $log = app(AuditLogger::class)->record('test.event', null, null, ['role' => 'trainee']);

        try {
            DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'tampered']);
            $this->fail('Une mise à jour directe du journal d\'audit aurait dû être refusée.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }
    }

    public function test_owner_can_view_audit_but_trainee_cannot(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);
        $organization = Organization::factory()->create(['workspace_id' => $workspace->id]);

        app(AuditLogger::class)->record(
            'organization.created',
            $workspace,
            $organization,
            ['organization_name' => $organization->name],
            $owner,
        );

        $this->actingAs($owner)
            ->get(route('workspace.audit.index', $workspace))
            ->assertOk()
            ->assertSee('organization.created')
            ->assertSee($owner->name);

        $trainee = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $trainee->id,
            'role' => 'trainee',
        ]);

        $this->actingAs($trainee)
            ->get(route('workspace.audit.index', $workspace))
            ->assertForbidden();
    }

    public function test_audit_is_isolated_by_workspace(): void
    {
        $alice = User::factory()->create();
        $workspaceA = $this->workspaceWith($alice);

        $bob = User::factory()->create();
        $workspaceB = $this->workspaceWith($bob);

        app(AuditLogger::class)->record('workspace.a.event', $workspaceA, null, [], $alice);
        app(AuditLogger::class)->record('workspace.b.event', $workspaceB, null, [], $bob);

        $this->actingAs($alice)
            ->get(route('workspace.audit.index', $workspaceA))
            ->assertOk()
            ->assertSee('workspace.a.event')
            ->assertDontSee('workspace.b.event');
    }

    public function test_metadata_allowlist_drops_sensitive_keys(): void
    {
        $log = app(AuditLogger::class)->record('test.event', null, null, [
            'password' => 'secret-password',
            'token' => 'secret-token',
            'email' => 'person@example.test',
            'role' => 'trainee',
            'reason' => 'test',
        ]);

        $this->assertSame(['role' => 'trainee', 'reason' => 'test'], $log->metadata);
        $this->assertStringNotContainsString('secret-password', json_encode($log->metadata));
        $this->assertStringNotContainsString('secret-token', json_encode($log->metadata));
        $this->assertStringNotContainsString('person@example.test', json_encode($log->metadata));
    }

    public function test_denied_workspace_access_is_audited(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $this->actingAs($user)
            ->get(route('workspace.dashboard', $workspace))
            ->assertForbidden();

        $log = AuditLog::where('action', 'workspace.access.denied')->firstOrFail();

        $this->assertSame($workspace->id, $log->workspace_id);
        $this->assertSame($user->id, $log->actor_id);
        $this->assertSame('not_member', $log->metadata['reason']);
    }

    public function test_audit_page_does_not_render_sensitive_values(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);

        app(AuditLogger::class)->record('test.event', $workspace, null, [
            'password' => 'super-secret',
            'role' => 'viewer',
        ], $owner);

        $this->actingAs($owner)
            ->get(route('workspace.audit.index', $workspace))
            ->assertOk()
            ->assertDontSee('super-secret')
            ->assertSee('viewer');
    }
}
