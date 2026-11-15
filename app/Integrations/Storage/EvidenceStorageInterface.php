<?php

namespace App\Integrations\Storage;

use Illuminate\Http\UploadedFile;

/**
 * Abstraction de stockage des preuves (T-040, DEC-ST-007).
 *
 * Toute implémentation doit retourner une clé de stockage (`storage_key`)
 * persistée en base ; aucun chemin absolu ne doit être exposé à l'interface.
 */
interface EvidenceStorageInterface
{
    /**
     * Stocke un fichier et retourne sa clé de stockage.
     */
    public function store(UploadedFile $file, string $workspaceId, string $evidenceId): string;

    /**
     * Supprime le fichier associé à une clé (utilisé uniquement lors d'un
     * nettoyage explicite ; l'archivage ne supprime rien).
     */
    public function delete(string $storageKey): void;
}
