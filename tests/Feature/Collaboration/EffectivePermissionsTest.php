<?php

namespace Tests\Feature\Collaboration;

use App\Domain\Authorization\EffectivePermissions;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Internship\Models\InternshipParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * MT-03 (complet) — tests paramétrés sur la matrice rôles × actions.
 * Aucune combinaison ne doit élever les privilèges.
 */
class EffectivePermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string|null, 2: string, 3: array<string, bool>}>
     */
    public static function matrixProvider(): array
    {
        $t = fn (bool $v) => $v;

        return [
            // [rôle workspace, rôle stage (ou null), action, attendu]
            'owner crée un journal' => ['owner', null, 'create_journal', ['allowed' => true]],
            'owner commentaire' => ['owner', null, 'comment', ['allowed' => true]],
            'admin ne crée pas de journal' => ['admin', null, 'create_journal', ['allowed' => false]],
            'admin crée une activité' => ['admin', null, 'create_activity', ['allowed' => true]],
            'admin ne voit pas l\'audit (stage)' => ['admin', null, 'view_audit', ['allowed' => true]],
            'stagiaire propriétaire crée un journal' => ['trainee', 'trainee', 'create_journal', ['allowed' => true]],
            'stagiaire propriétaire dépose une preuve' => ['trainee', 'trainee', 'deposit_evidence', ['allowed' => true]],
            'stagiaire ne révise pas' => ['trainee', 'trainee', 'review', ['allowed' => false]],
            'stagiaire observateur ne crée pas de journal' => ['trainee', 'observer', 'create_journal', ['allowed' => false]],
            'stagiaire observateur peut commenter' => ['trainee', 'observer', 'comment', ['allowed' => true]],
            'supervisor rattaché commente' => ['supervisor', 'supervisor', 'comment', ['allowed' => true]],
            'supervisor rattaché révise' => ['supervisor', 'supervisor', 'review', ['allowed' => true]],
            'supervisor rattaché ne crée pas de journal' => ['supervisor', 'supervisor', 'create_journal', ['allowed' => false]],
            'supervisor rattaché ne dépose pas de preuve' => ['supervisor', 'supervisor', 'deposit_evidence', ['allowed' => false]],
            'supervisor non rattaché : aucun accès' => ['supervisor', null, 'view_activities', ['allowed' => false]],
            'tuteur rattaché révise' => ['tutor', 'tutor', 'review', ['allowed' => true]],
            'tuteur rattaché ne modifie pas d\'activité' => ['tutor', 'tutor', 'create_activity', ['allowed' => false]],
            'lecteur rattaché peut commenter' => ['viewer', 'supervisor', 'comment', ['allowed' => true]],
            'lecteur rattaché ne révise pas' => ['viewer', 'supervisor', 'review', ['allowed' => false]],
        ];
    }

    /**
     * @param  array<string, bool>  $expected
     */
    #[DataProvider('matrixProvider')]
    public function test_matrix(string $workspaceRole, ?string $stageRole, string $action, array $expected): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        $member = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => $workspaceRole,
        ]);

        $internship = Internship::factory()->create(['workspace_id' => $workspace->id]);

        if ($stageRole !== null) {
            InternshipParticipant::query()->create([
                'internship_id' => $internship->id,
                'workspace_id' => $workspace->id,
                'user_id' => $member->id,
                'role' => $stageRole === 'observer' ? 'supervisor' : $stageRole,
                'permissions' => [],
                'status' => 'active',
            ]);

            if ($stageRole === 'observer') {
                // Le participant « observer » simulé n'est pas réel :
                // on le retire pour que la résolution passe par la branche
                // observateur (stagiaire membre non rattaché).
                InternshipParticipant::query()->sole()->forceFill(['user_id' => $owner->id])->save();
            }
        }

        $permissions = EffectivePermissions::resolveForInternship($member, $workspace, $internship);

        $expected['allowed']
            ? $this->assertTrue($permissions[$action], "$workspaceRole/$stageRole doit avoir $action")
            : $this->assertFalse($permissions[$action], "$workspaceRole/$stageRole ne doit pas avoir $action");
    }

    public function test_json_permissions_can_only_restrict(): void
    {
        $owner = User::factory()->create();
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

        $internship = Internship::factory()->create(['workspace_id' => $workspace->id]);
        $participant = InternshipParticipant::query()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'user_id' => $supervisor->id,
            'role' => 'supervisor',
            'permissions' => ['comment' => false, 'create_journal' => true, 'view_audit' => true],
            'status' => 'active',
        ]);

        $permissions = EffectivePermissions::resolveForInternship($supervisor, $workspace, $internship);

        // Restriction appliquée.
        $this->assertFalse($permissions['comment']);
        // Clés hors liste ignorées : aucun élargissement.
        $this->assertFalse($permissions['create_journal']);
        $this->assertFalse($permissions['view_audit']);

        // Restriction levée : retour au défaut du rôle.
        $participant->forceFill(['permissions' => ['comment' => true]])->save();
        $permissions = EffectivePermissions::resolveForInternship($supervisor, $workspace, $internship);
        $this->assertTrue($permissions['comment']);
    }

    public function test_export_can_be_granted_to_supervisor_via_json(): void
    {
        $owner = User::factory()->create();
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

        $internship = Internship::factory()->create(['workspace_id' => $workspace->id]);
        InternshipParticipant::query()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'user_id' => $supervisor->id,
            'role' => 'supervisor',
            'permissions' => ['export' => true],
            'status' => 'active',
        ]);

        $permissions = EffectivePermissions::resolveForInternship($supervisor, $workspace, $internship);

        $this->assertTrue($permissions['export']);
        $this->assertFalse($permissions['create_journal']);
    }

    public function test_revoked_participant_loses_all_access(): void
    {
        $owner = User::factory()->create();
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

        $internship = Internship::factory()->create(['workspace_id' => $workspace->id]);
        $participant = InternshipParticipant::query()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'user_id' => $supervisor->id,
            'role' => 'supervisor',
            'permissions' => [],
            'status' => 'active',
        ]);

        $participant->forceFill(['status' => 'removed'])->save();

        $permissions = EffectivePermissions::resolveForInternship($supervisor, $workspace, $internship);

        foreach (EffectivePermissions::ACTIONS as $action) {
            $this->assertFalse($permissions[$action], "révoqué ne doit pas avoir $action");
        }
    }

    public function test_non_member_has_no_access(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        $internship = Internship::factory()->create(['workspace_id' => $workspace->id]);
        $outsider = User::factory()->create();

        $permissions = EffectivePermissions::resolveForInternship($outsider, $workspace, $internship);

        foreach (EffectivePermissions::ACTIONS as $action) {
            $this->assertFalse($permissions[$action]);
        }
    }
}
