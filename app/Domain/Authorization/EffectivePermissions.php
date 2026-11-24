<?php

namespace App\Domain\Authorization;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Internship\Models\Internship;
use App\Domain\Internship\Models\InternshipParticipant;

/**
 * Permissions effectives de stage (T-052, matrice rôles-permissions §5) :
 * intersection (rôle workspace ∩ rôle stage ∩ visibilité ressource).
 *
 * Règles :
 * - un utilisateur non membre du workspace n'a aucun accès ;
 * - un rôle de stage ne dépasse jamais le rôle de workspace (intersection) ;
 * - `internship_participants.permissions` (JSON) ne peut que restreindre,
 *   sauf `export` pour supervisor/tutor (🔶 « autorisé » de la matrice,
 *   octroi explicite) ;
 * - un participant révoqué (`removed`) perd tout accès immédiatement ;
 * - supervisor/tutor/viewer n'accèdent qu'aux stages auxquels ils sont
 *   rattachés (portée « stage attribué » de la matrice) ;
 * - owner/admin ne sont pas plafonnés par un éventuel rôle de participant
 *   (rôle de gestion, DEC documentée dans le CHANGELOG 1.4.0).
 */
final class EffectivePermissions
{
    /**
     * Actions évaluées.
     *
     * @var list<string>
     */
    public const ACTIONS = [
        'view_internship',
        'view_journal',
        'view_activities',
        'view_evidences',
        'comment',
        'review',
        'create_activity',
        'create_journal',
        'manage_planning',
        'create_internship',
        'deposit_evidence',
        'assess',
        'export',
        'view_audit',
        'manage_users',
    ];

    /**
     * Valeurs par défaut de la matrice (§3) par rôle de workspace.
     *
     * @var array<string, array<string, bool>>
     */
    private const WORKSPACE_DEFAULTS = [
        'owner' => [
            'view_internship' => true,
            'view_journal' => true,
            'view_activities' => true,
            'view_evidences' => true,
            'comment' => true,
            'review' => false,
            'create_activity' => true,
            'create_journal' => true,
            'manage_planning' => true,
            'create_internship' => true,
            'deposit_evidence' => true,
            'assess' => false,
            'export' => true,
            'view_audit' => true,
            'manage_users' => true,
        ],
        'admin' => [
            'view_internship' => true,
            'view_journal' => true,
            'view_activities' => true,
            'view_evidences' => true,
            'comment' => true,
            'review' => false,
            'create_activity' => true,
            'create_journal' => false,
            'manage_planning' => true,
            'create_internship' => true,
            'deposit_evidence' => true,
            'assess' => false,
            'export' => true,
            'view_audit' => true,
            'manage_users' => true,
        ],
        'trainee' => [
            'view_internship' => true,
            'view_journal' => true,
            'view_activities' => true,
            'view_evidences' => true,
            'comment' => true,
            'review' => false,
            'create_activity' => true,
            'create_journal' => true,
            'manage_planning' => true,
            'create_internship' => true,
            'deposit_evidence' => true,
            'assess' => false,
            'export' => true,
            'view_audit' => false,
            'manage_users' => false,
        ],
        'supervisor' => [
            'view_internship' => true,
            'view_journal' => true,
            'view_activities' => true,
            'view_evidences' => true,
            'comment' => true,
            'review' => true,
            'create_activity' => false,
            'create_journal' => false,
            'manage_planning' => false,
            'create_internship' => false,
            'deposit_evidence' => false,
            'assess' => true,
            'export' => false,
            'view_audit' => false,
            'manage_users' => false,
        ],
        'tutor' => [
            'view_internship' => true,
            'view_journal' => true,
            'view_activities' => true,
            'view_evidences' => true,
            'comment' => true,
            'review' => true,
            'create_activity' => false,
            'create_journal' => false,
            'manage_planning' => false,
            'create_internship' => false,
            'deposit_evidence' => false,
            'assess' => true,
            'export' => false,
            'view_audit' => false,
            'manage_users' => false,
        ],
        'viewer' => [
            'view_internship' => true,
            'view_journal' => true,
            'view_activities' => true,
            'view_evidences' => true,
            'comment' => true,
            'review' => false,
            'create_activity' => false,
            'create_journal' => false,
            'manage_planning' => false,
            'create_internship' => false,
            'deposit_evidence' => false,
            'assess' => false,
            'export' => false,
            'view_audit' => false,
            'manage_users' => false,
        ],
    ];

    /**
     * Plafond par rôle de stage (MLD internship_participants.role).
     * `observer` = membre workspace consulteur non rattaché au stage.
     *
     * @var array<string, array<string, bool>>
     */
    private const STAGE_DEFAULTS = [
        'trainee' => [
            'view_internship' => true,
            'view_journal' => true,
            'view_activities' => true,
            'view_evidences' => true,
            'comment' => true,
            'review' => false,
            'create_activity' => true,
            'create_journal' => true,
            'manage_planning' => true,
            'create_internship' => true,
            'deposit_evidence' => true,
            'assess' => false,
            'export' => true,
        ],
        'supervisor' => [
            'view_internship' => true,
            'view_journal' => true,
            'view_activities' => true,
            'view_evidences' => true,
            'comment' => true,
            'review' => true,
            'create_activity' => false,
            'create_journal' => false,
            'manage_planning' => false,
            'create_internship' => false,
            'deposit_evidence' => false,
            'assess' => true,
            'export' => false,
        ],
        'tutor' => [
            'view_internship' => true,
            'view_journal' => true,
            'view_activities' => true,
            'view_evidences' => true,
            'comment' => true,
            'review' => true,
            'create_activity' => false,
            'create_journal' => false,
            'manage_planning' => false,
            'create_internship' => false,
            'deposit_evidence' => false,
            'assess' => true,
            'export' => false,
        ],
        'observer' => [
            'view_internship' => true,
            'view_journal' => true,
            'view_activities' => true,
            'view_evidences' => true,
            'comment' => true,
            'review' => false,
            'create_activity' => false,
            'create_journal' => false,
            'manage_planning' => false,
            'create_internship' => false,
            'deposit_evidence' => false,
            'assess' => false,
            'export' => false,
        ],
    ];

    /**
     * Clés restreignables par `internship_participants.permissions` (§4).
     *
     * @var list<string>
     */
    public const RESTRICTABLE = [
        'view_journal',
        'view_activities',
        'view_evidences',
        'comment',
        'review',
        'export',
    ];

    /**
     * @return array<string, bool>
     */
    public static function none(): array
    {
        return array_fill_keys(self::ACTIONS, false);
    }

    /**
     * Résolution pour un utilisateur sur un stage donné.
     *
     * @return array<string, bool>
     */
    public static function resolveForInternship(User $user, Workspace $workspace, Internship $internship): array
    {
        $workspaceRole = $workspace->roleFor($user);

        if ($workspaceRole === null) {
            return self::none();
        }

        // owner/admin : rôle de gestion, non plafonné par un rôle de stage.
        if (in_array($workspaceRole, ['owner', 'admin'], true)) {
            return self::applyJson(self::WORKSPACE_DEFAULTS[$workspaceRole], null);
        }

        $participant = $internship->participants()
            ->where('user_id', $user->id)
            ->first();

        $stageRole = null;

        if ($participant !== null) {
            if ($participant->status !== 'active') {
                return self::none();
            }

            $stageRole = $participant->role;
        } elseif ($internship->trainee_id === $user->id) {
            $stageRole = 'trainee';
        } elseif (in_array($workspaceRole, ['supervisor', 'tutor', 'viewer'], true)) {
            // Portée « stage attribué » : sans rattachement actif, aucun accès.
            return self::none();
        } else {
            $stageRole = 'observer';
        }

        $effective = [];

        foreach (self::ACTIONS as $action) {
            $effective[$action] = (self::WORKSPACE_DEFAULTS[$workspaceRole][$action] ?? false)
                && (self::STAGE_DEFAULTS[$stageRole][$action] ?? false);
        }

        return self::applyJson($effective, $participant);
    }

    /**
     * Applique les restrictions JSON ; seul `export` peut être octroyé
     * explicitement à un supervisor/tutor (🔶 de la matrice).
     *
     * @param  array<string, bool>  $effective
     */
    private static function applyJson(array $effective, ?InternshipParticipant $participant): array
    {
        $json = $participant?->permissions ?? [];

        foreach ($json as $key => $value) {
            if (! in_array($key, self::RESTRICTABLE, true)) {
                continue;
            }

            if ($key === 'export'
                && $participant !== null
                && in_array($participant->role, ['supervisor', 'tutor'], true)) {
                $effective[$key] = $effective[$key] || (bool) $value;

                continue;
            }

            $effective[$key] = $effective[$key] && (bool) $value;
        }

        return $effective;
    }
}
