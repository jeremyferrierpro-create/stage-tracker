<?php

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WorkspaceMember;

/**
 * Référentiels de compétences : ressource globale (T-021/T-022).
 *
 * Décision DEC-ST-015 (2026-11-08, arbitrage propriétaire) : dans la V1,
 * l'administration est confiée aux propriétaires et administrateurs d'au
 * moins un espace de travail actif (matrice identique à celle des espaces).
 * Aucune modification n'affecte un seul tenant : les versions publiées sont
 * partagées par tous et immuables (INV-08).
 */
class CompetencyFrameworkPolicy
{
    public function view(User $user): bool
    {
        return true;
    }

    public function manage(User $user): bool
    {
        return WorkspaceMember::query()
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'admin'])
            ->exists();
    }
}
