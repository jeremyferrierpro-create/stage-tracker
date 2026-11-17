<?php

namespace App\Console\Commands;

use App\Support\Backup\BackupService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Restauration (T-065, MT-12) : contrôle d'empreintes obligatoire, puis
 * réécriture de la base et des fichiers. `--dry-run` vérifie les empreintes
 * sans modifier l'environnement.
 */
class RestoreCommand extends Command
{
    protected $signature = 'app:restore
                            {path : Répertoire de l\'archive de sauvegarde}
                            {--dry-run : Vérifier les empreintes sans restaurer}';

    protected $description = 'Restaure la base et les fichiers depuis une sauvegarde vérifiée (T-065)';

    public function handle(BackupService $service): int
    {
        $path = (string) $this->argument('path');

        if (! is_dir($path)) {
            $this->error("Archive introuvable : {$path}");

            return self::FAILURE;
        }

        if ((bool) $this->option('dry-run')) {
            $this->info('Contrôle des empreintes (sans modification) …');

            try {
                $result = $service->verify($path);
            } catch (RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $this->info("Empreintes validées : {$result['tables']} tables, {$result['files']} fichiers.");

            return self::SUCCESS;
        }

        if (! $this->confirm('La restauration va REMPLACER toutes les données. Continuer ?')) {
            $this->info('Annulé.');

            return self::SUCCESS;
        }

        $this->info('Restauration depuis '.$path.' …');

        try {
            $result = $service->restore($path);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Élément', 'Détail'],
            [
                ['Empreintes', 'vérifiées avant restauration'],
                ['Tables restaurées', $result['tables']],
                ['Lignes', $result['rows']],
                ['Fichiers restaurés', $result['files']],
            ],
        );

        $this->info('Restauration terminée.');

        return self::SUCCESS;
    }
}
