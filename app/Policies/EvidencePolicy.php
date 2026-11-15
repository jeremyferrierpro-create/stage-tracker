<?php

namespace App\Policies;

use App\Domain\Evidence\Models\Evidence;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;

/**
 * Preuves (T-041). MT-07/MT-08 : une preuve `private` n'est visible que de
 * son propriétaire (et des owners/admins de l'espace) ; une preuve `stage`
 * est visible des membres actifs de l'espace rattachés au stage concerné,
 * une preuve `shared` de tous les membres actifs.
 */
class EvidencePolicy
{
    public function view(User $user, Evidence $evidence): bool
    {
        $workspace = Workspace::query()->find($evidence->workspace_id);
        $role = $workspace?->roleFor($user);

        if ($role === null) {
            return false;
        }

        if (in_array($role, ['owner', 'admin'], true)) {
            return true;
        }

        if ($evidence->owner_id === $user->id) {
            return true;
        }

        return in_array($evidence->visibility, ['stage', 'shared'], true);
    }

    public function manage(User $user, Evidence $evidence): bool
    {
        $workspace = Workspace::query()->find($evidence->workspace_id);
        $role = $workspace?->roleFor($user);

        if ($role !== null && in_array($role, ['owner', 'admin'], true)) {
            return true;
        }

        return $evidence->owner_id === $user->id;
    }
}
