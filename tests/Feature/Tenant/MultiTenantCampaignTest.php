<?php

namespace Tests\Feature\Tenant;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Evaluation\Models\Competency;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Campagne de tests phase 1 (T-012) : preuve des invariants multi-tenant.
 * MT-01, MT-02, MT-03 et MT-10 du tests/specifications. Le périmètre porte sur
 * les modules livrés à ce stade (organisations, membres, audit) ; MT-03 sera
 * complété quand les modules métier (stages/journal/activités) existeront.
 */
class MultiTenantCampaignTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceWithMember(
        User $user,
        string $role = 'owner',
        ?string $name = null
    ): Workspace {
        $workspace = Workspace::factory()->create([
            'owner_id' => $user->id,
            'name' => $name,
        ]);

        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $workspace;
    }

    // ---------------------------------------------------------------- MT-01

    public function test_mt01_a_member_never_sees_data_of_another_workspace(): void
    {
        $alien = User::factory()->create();
        $workspaceA = $this->workspaceWithMember($alien, 'admin', 'Espace A');
        Competency::factory()->create([
            'workspace_id' => $workspaceA->id,
            'code' => 'A1',
            'title' => 'Compétence A',
        ]);

        $workspaceB = $this->workspaceWithMember(User::factory()->create(), 'owner', 'Espace B');
        Competency::factory()->create([
            'workspace_id' => $workspaceB->id,
            'code' => 'B1',
            'title' => 'Compétence B',
        ]);

        // Liste de A : la compétence de B n'apparaît pas.
        $this->actingAs($alien)
            ->get(route('workspace.competencies.index', $workspaceA))
            ->assertOk()
            ->assertSee('Compétence A', false)
            ->assertDontSee('Compétence B', false);

        // Ressource de B accédée par ID direct : 403 (non-membre de B) et
        // aucune fuite d'existence dans la réponse.
        $response = $this->actingAs($alien)
            ->get(route('workspace.competencies.index', $workspaceB))
            ->assertForbidden();

        $this->assertStringNotContainsString('Compétence B', (string) $response->getContent());
        $this->assertStringNotContainsString($workspaceB->name, (string) $response->getContent());

        // Journal de B : 403 également.
        $this->actingAs($alien)
            ->get(route('workspace.audit.index', $workspaceB))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------- MT-02

    public function test_mt02_authenticated_non_member_is_forbidden_without_existence_leak(): void
    {
        $intruder = User::factory()->create(); // membre d'aucun workspace
        $workspace = $this->workspaceWithMember(User::factory()->create(), 'owner', 'Espace Cible');
        Competency::factory()->create([
            'workspace_id' => $workspace->id,
            'code' => 'C1',
            'title' => 'Compétence Cible',
        ]);

        $requests = [
            ['get', 'workspace.dashboard', [$workspace]],
            ['get', 'workspace.competencies.index', [$workspace]],
            ['get', 'workspace.competencies.create', [$workspace]],
            ['post', 'workspace.competencies.store', [$workspace]],
            ['get', 'workspace.members.index', [$workspace]],
            ['post', 'workspace.invitations.store', [$workspace]],
            ['get', 'workspace.audit.index', [$workspace]],
        ];

        $payloads = [
            'workspace.competencies.store' => [
                'code' => 'X9',
                'title' => 'Tentative Intrus',
            ],
            'workspace.invitations.store' => [
                'email' => 'intrus@example.test',
                'role' => 'trainee',
            ],
        ];

        foreach ($requests as [$method, $routeName, $params]) {
            $response = $this->actingAs($intruder)->{$method}(route($routeName, $params), $payloads[$routeName] ?? []);

            $response->assertStatus(403, "La route {$routeName} doit être interdite à un non-membre.");

            $this->assertStringNotContainsString(
                $workspace->name,
                (string) $response->getContent(),
                "La route {$routeName} ne doit pas révéler l'existence du workspace."
            );
        }

        // Aucun objet n'a été créé malgré les POST.
        $this->assertSame(1, Competency::count());
        $this->assertSame(0, $workspace->invitations()->count());
    }

    // ---------------------------------------------------------------- MT-03

    public function test_mt03_stage_roles_cannot_administer_the_workspace(): void
    {
        foreach (Workspace::ROLES as $role) {
            if (in_array($role, ['owner', 'admin'], true)) {
                continue;
            }

            $member = User::factory()->create();
            $workspace = $this->workspaceWithMember(
                User::factory()->create(),
                'owner',
                "Espace {$role}-".uniqid()
            );
            WorkspaceMember::factory()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $member->id,
                'role' => $role,
            ]);

            // En lecture : la gestion des compétences est réservée aux
            // administrateurs (comme les membres et l'audit).
            $this->actingAs($member)
                ->get(route('workspace.competencies.index', $workspace))
                ->assertForbidden();

            // ... mais aucune écriture ni administration n'est possible.
            $this->actingAs($member)
                ->post(route('workspace.competencies.store', $workspace), [
                    'code' => 'X1',
                    'title' => 'Interdit',
                ])
                ->assertForbidden();

            $this->actingAs($member)
                ->post(route('workspace.invitations.store', $workspace), [
                    'email' => "{$role}+invite@example.test",
                    'role' => 'trainee',
                ])
                ->assertForbidden();

            $this->actingAs($member)
                ->get(route('workspace.members.index', $workspace))
                ->assertForbidden();

            $this->actingAs($member)
                ->get(route('workspace.audit.index', $workspace))
                ->assertForbidden();

            $this->assertSame(0, Competency::count(), "Le rôle {$role} ne doit rien créer.");
            $this->assertSame(0, $workspace->invitations()->count(), "Le rôle {$role} ne doit rien inviter.");
        }
    }

    public function test_mt03_admin_and_owner_can_administer(): void
    {
        $admin = User::factory()->create();
        $workspace = $this->workspaceWithMember($admin, 'admin', 'Espace ADM');

        $this->actingAs($admin)
            ->post(route('workspace.competencies.store', $workspace), [
                'code' => 'ADM1',
                'title' => 'Compétence ADM',
            ])
            ->assertRedirect();

        $this->assertSame(1, $workspace->competencies()->count());

        $this->actingAs($admin)
            ->post(route('workspace.invitations.store', $workspace), [
                'email' => 'invite@example.test',
                'role' => 'trainee',
            ])
            ->assertRedirect();

        $this->assertSame(1, $workspace->invitations()->count());

        $this->actingAs($admin)
            ->get(route('workspace.audit.index', $workspace))
            ->assertOk();
    }

    // ---------------------------------------------------------------- MT-10

    public function test_mt10_revoked_membership_loses_access_but_history_is_kept(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner, 'owner', 'Espace Historique');

        $removed = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $removed->id,
            'role' => 'admin',
        ]);

        // Le membre (admin) crée des données historiques via l'application,
        // ce qui trace aussi le journal d'audit.
        $this->actingAs($removed)
            ->post(route('workspace.competencies.store', $workspace), [
                'code' => 'H1',
                'title' => 'Compétence Historique',
            ])
            ->assertRedirect();

        $competency = $workspace->competencies()->where('code', 'H1')->sole();

        // Entrées d'audit écrites avant la révocation.
        $preRevocationAuditIds = AuditLog::query()
            ->where('workspace_id', $workspace->id)
            ->pluck('id')
            ->all();
        $this->assertContains(
            AuditLog::query()->where('action', 'competency.created')->value('id'),
            $preRevocationAuditIds
        );

        // Révocation : aucune cascade destructive (simulée comme fera T-053).
        WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $removed->id)
            ->update(['status' => 'removed']);

        // Accès perdu.
        $this->actingAs($removed)
            ->get(route('workspace.dashboard', $workspace))
            ->assertForbidden();

        // L'historique des données reste présent et consultable par le propriétaire.
        $this->assertDatabaseHas('competencies', [
            'id' => $competency->id,
            'workspace_id' => $workspace->id,
            'code' => 'H1',
        ]);

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $removed->id,
            'status' => 'removed',
        ]);

        // Aucune entrée d'audit antérieure n'est supprimée (journal append-only).
        foreach ($preRevocationAuditIds as $auditId) {
            $this->assertDatabaseHas('audit_logs', ['id' => $auditId]);
        }

        $this->actingAs($owner)
            ->get(route('workspace.competencies.index', $workspace))
            ->assertOk()
            ->assertSee('Compétence Historique', false);

        // La tentative de l'ancien membre est tracée dans le journal.
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->id,
            'action' => 'workspace.access.denied',
        ]);
    }
}
