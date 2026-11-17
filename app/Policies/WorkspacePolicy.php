<?php

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;

/**
 * Matrice rôles/permissions de base (T-006, ADR-002).
 * Un rôle de stage ne peut jamais dépasser le rôle de workspace.
 */
class WorkspacePolicy
{
    private const MANAGEMENT_ROLES = ['owner', 'admin'];

    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->roleFor($user) !== null;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return in_array($workspace->roleFor($user), self::MANAGEMENT_ROLES, true);
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return in_array($workspace->roleFor($user), self::MANAGEMENT_ROLES, true);
    }

    public function manageInternships(User $user, Workspace $workspace): bool
    {
        return in_array($workspace->roleFor($user), self::MANAGEMENT_ROLES, true);
    }

    public function viewAudit(User $user, Workspace $workspace): bool
    {
        return in_array($workspace->roleFor($user), self::MANAGEMENT_ROLES, true);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }
}
