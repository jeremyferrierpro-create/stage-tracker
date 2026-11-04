<?php

namespace App\Domain\Identity;

use App\Domain\Identity\Models\Workspace;

/**
 * Workspace courant de la requête, résolu par le middleware
 * EnsureWorkspaceMember. Jamais alimenté par une entrée utilisateur directe.
 */
final class WorkspaceContext
{
    private ?Workspace $workspace = null;

    public function set(?Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function get(): ?Workspace
    {
        return $this->workspace;
    }

    public function id(): ?string
    {
        return $this->workspace?->getKey();
    }

    public function check(): bool
    {
        return $this->workspace !== null;
    }

    public function clear(): void
    {
        $this->workspace = null;
    }
}
