<?php

namespace App\Console\Commands;

use App\Support\Backup\BackupService;
use Illuminate\Console\Command;

/**
 * Sauvegarde complète (T-065) : base PostgreSQL (JSONL par table) + fichiers
 * du disque privé, avec manifeste d'empreintes SHA-256.
 */
class BackupCommand extends Command
{
    protected $signature = 'app:backup
                            {--path= : Répertoire cible (défaut : storage/app/backups/<horodatage>)}';

    protected $description = 'Sauvegarde la base et les fichiers de preuves avec empreintes SHA-256 (T-065)';

    public function handle(BackupService $service): int
    {
        $path = (string) ($this->option('path') ?: storage_path('app/backups/'.now()->format('Ymd_His')));

        $this->info('Sauvegarde vers '.$path.' …');

        $result = $service->backup($path);

        $this->table(
            ['Élément', 'Détail'],
            [
                ['Archive', $result['path']],
                ['Tables', count($result['manifest']['tables'])],
                ['Lignes', $result['rows']],
                ['Fichiers', count($result['manifest']['files'])],
                ['Empreintes', 'SHA-256 écrites dans manifest.json'],
            ],
        );

        $this->info('Vérification recommandée : php artisan app:restore '.$path.' --dry-run');

        return self::SUCCESS;
    }
}
