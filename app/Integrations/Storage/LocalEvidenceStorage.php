<?php

namespace App\Integrations\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stockage local des preuves (T-040). Les fichiers sont écrits sur le disque
 * `local` (privé, hors webroot) sous `evidences/{workspace}/{evidence}/`, avec
 * un nom aléatoire (uuid) et l'extension d'origine : l'URL n'est jamais
 * devinable et le téléchargement passe toujours par le contrôleur.
 */
class LocalEvidenceStorage implements EvidenceStorageInterface
{
    private const DISK = 'local';

    public function store(UploadedFile $file, string $workspaceId, string $evidenceId): string
    {
        $directory = 'evidences/'.$workspaceId.'/'.$evidenceId;
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;

        $storedPath = $file->storeAs($directory, $filename, self::DISK);

        if ($storedPath === false) {
            throw new \RuntimeException('Échec du stockage du fichier de preuve.');
        }

        return $storedPath;
    }

    public function delete(string $storageKey): void
    {
        Storage::disk(self::DISK)->delete($storageKey);
    }
}
