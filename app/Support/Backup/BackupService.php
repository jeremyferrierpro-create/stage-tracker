<?php

namespace App\Support\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Sauvegarde et restauration (T-065, MT-12) : base (JSONL par table) +
 * fichiers de preuves/rapports (disque privé), avec empreintes SHA-256
 * contrôlées avant toute restauration. Le stockage des archives est hors
 * du disque sauvegardé (storage/app/backups).
 */
class BackupService
{
    private const MANIFEST = 'manifest.json';

    /**
     * Répertoires du disque privé exclus de la sauvegarde (l'archive
     * elle-même vit sur le disque système, jamais dans le disque local).
     *
     * @var list<string>
     */
    private const EXCLUDED_DIRS = ['backups'];

    /**
     * @return array{path: string, manifest: array<string, mixed>}
     */
    public function backup(string $targetDir): array
    {
        if (is_dir($targetDir)) {
            throw new RuntimeException("Le répertoire cible existe déjà : {$targetDir}");
        }

        $tables = $this->tables();

        foreach ([$targetDir, $targetDir.'/db'] as $dir) {
            if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
                throw new RuntimeException("Impossible de créer {$dir}");
            }
        }

        $tableEntries = [];
        $totalRows = 0;

        foreach ($tables as $table) {
            $rows = DB::table($table)->get()->map(fn ($row): array => (array) $row)->all();
            $file = 'db/'.$table.'.jsonl';
            $this->write($targetDir.'/'.$file, $this->encode($rows));

            $tableEntries[] = [
                'table' => $table,
                'file' => $file,
                'rows' => count($rows),
                'sha256' => hash_file('sha256', $targetDir.'/'.$file),
            ];
            $totalRows += count($rows);
        }

        $disk = Storage::disk('local');
        $fileEntries = [];

        foreach ($disk->allFiles() as $path) {
            if ($this->isExcluded($path)) {
                continue;
            }

            $target = $targetDir.'/files/'.$path;
            $directory = dirname($target);
            if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
                throw new RuntimeException("Impossible de créer {$directory}");
            }
            copy($disk->path($path), $target);

            $fileEntries[] = [
                'path' => $path,
                'sha256' => hash_file('sha256', $target),
            ];
        }

        $manifest = [
            'created_at' => now()->toIso8601String(),
            'database' => config('database.connections.pgsql.database'),
            'tables' => $tableEntries,
            'files' => $fileEntries,
        ];

        $this->write($targetDir.'/'.self::MANIFEST, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'path' => $targetDir,
            'manifest' => $manifest,
            'rows' => $totalRows,
        ];
    }

    /**
     * Vérifie toutes les empreintes (sans modification) — MT-12.
     *
     * @return array{verified: true, tables: int, files: int}
     */
    public function verify(string $sourceDir): array
    {
        $manifest = $this->manifest($sourceDir);

        foreach ($manifest['tables'] as $entry) {
            $this->assertFingerprint($sourceDir.'/'.$entry['file'], $entry['sha256'], $entry['file']);
        }

        foreach ($manifest['files'] as $entry) {
            $this->assertFingerprint($sourceDir.'/files/'.$entry['path'], $entry['sha256'], $entry['path']);
        }

        return [
            'verified' => true,
            'tables' => count($manifest['tables']),
            'files' => count($manifest['files']),
        ];
    }

    /**
     * Restaure base + fichiers après contrôle d'empreintes. Les tables sont
     * vidées puis réinsérées dans l'ordre topologique des clés étrangères ;
     * les séquences sont recadrées sur les identifiants restaurés.
     *
     * @return array{verified: true, tables: int, rows: int, files: int}
     */
    public function restore(string $sourceDir): array
    {
        $this->verify($sourceDir);
        $manifest = $this->manifest($sourceDir);

        // Une seule instruction : TRUNCATE CASCADE couvre les dépendances.
        $tables = $this->tables();
        $quoted = implode(', ', array_map(fn (string $table): string => '"'.$table.'"', $tables));
        DB::statement("TRUNCATE TABLE {$quoted} RESTART IDENTITY CASCADE");

        $rows = 0;

        foreach ($this->topologicalOrder($tables) as $table) {
            $entry = null;
            foreach ($manifest['tables'] as $candidate) {
                if ($candidate['table'] === $table) {
                    $entry = $candidate;
                    break;
                }
            }

            if ($entry === null) {
                continue; // table créée après la sauvegarde : laissée vide
            }

            $lines = file($sourceDir.'/'.$entry['file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $batch = [];

            foreach ($lines as $line) {
                /** @var array<string, mixed> $row */
                $row = json_decode((string) $line, true, 512, JSON_THROW_ON_ERROR);
                $batch[] = $row;

                if (count($batch) === 100) {
                    DB::table($table)->insert($batch);
                    $rows += count($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                DB::table($table)->insert($batch);
                $rows += count($batch);
            }
        }

        $this->resetSequences($tables);

        // Fichiers : remplacement intégral par le contenu sauvegardé.
        $disk = Storage::disk('local');
        $existing = array_values(array_filter($disk->allFiles(), fn (string $path): bool => ! $this->isExcluded($path)));
        if ($existing !== []) {
            $disk->delete($existing);
        }

        foreach ($manifest['files'] as $entry) {
            $source = $sourceDir.'/files/'.$entry['path'];
            $disk->put($entry['path'], (string) file_get_contents($source));
        }

        return [
            'verified' => true,
            'tables' => count($manifest['tables']),
            'rows' => $rows,
            'files' => count($manifest['files']),
        ];
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        /** @var list<array<string, mixed>> $all */
        $all = Schema::getTables();

        return collect($all)
            ->filter(fn (array $table): bool => ($table['schema'] ?? 'public') === 'public')
            ->map(fn (array $table): string => (string) $table['name'])
            ->values()
            ->all();
    }

    /**
     * Ordre topologique : les tables référencées d'abord (insertion sans
     * violer les FK), via Schema::getForeignKeys.
     *
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function topologicalOrder(array $tables): array
    {
        $references = [];

        foreach ($tables as $table) {
            $references[$table] = [];
        }

        foreach ($tables as $table) {
            /** @var list<array<string, mixed>> $foreignKeys */
            $foreignKeys = Schema::getForeignKeys($table);

            foreach ($foreignKeys as $foreignKey) {
                $target = $foreignKey['foreign_table'] ?? null;

                if (is_string($target) && isset($references[$target]) && $target !== $table) {
                    $references[$table][] = $target;
                }
            }
        }

        $ordered = [];
        $pending = $tables;

        while ($pending !== []) {
            $ready = array_values(array_filter(
                $pending,
                fn (string $table): bool => array_filter(
                    $references[$table],
                    fn (string $target): bool => ! in_array($target, $ordered, true),
                ) === [],
            ));

            if ($ready === []) {
                // Cycle imprévu : on échoue explicitement (jamais de perte
                // silencieuse de données).
                throw new RuntimeException('Cycle de clés étrangères imprévu : '.implode(', ', $pending));
            }

            foreach ($ready as $table) {
                $ordered[] = $table;
            }

            $pending = array_values(array_diff($pending, $ready));
        }

        return $ordered;
    }

    /**
     * @param  list<string>  $tables
     */
    private function resetSequences(array $tables): void
    {
        /** @var list<object{table_name: string, column_name: string}> $serials */
        $serials = DB::select(
            "SELECT table_name, column_name FROM information_schema.columns
             WHERE table_schema = 'public' AND column_default LIKE 'nextval%'",
        );

        foreach ($serials as $serial) {
            $table = $serial->table_name;

            if (! in_array($table, $tables, true)) {
                continue;
            }

            /** @var object{max: ?int} $row */
            $row = DB::selectOne("SELECT COALESCE(MAX(\"{$serial->column_name}\"), 0) AS max FROM \"{$table}\"");

            if (($row->max ?? 0) > 0) {
                DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', '{$serial->column_name}'), {$row->max})");
            }
        }
    }

    private function isExcluded(string $path): bool
    {
        foreach (self::EXCLUDED_DIRS as $dir) {
            if ($path === $dir || str_starts_with($path, $dir.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function encode(array $rows): string
    {
        $lines = [];

        foreach ($rows as $row) {
            $lines[] = json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return implode("\n", $lines).($lines === [] ? '' : "\n");
    }

    private function write(string $path, string $content): void
    {
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException("Échec d'écriture : {$path}");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(string $sourceDir): array
    {
        $file = $sourceDir.'/'.self::MANIFEST;

        if (! is_file($file)) {
            throw new RuntimeException("Manifest introuvable : {$file}");
        }

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        return $manifest;
    }

    private function assertFingerprint(string $path, string $expected, string $label): void
    {
        if (! is_file($path)) {
            throw new RuntimeException("Élément manquant dans l'archive : {$label}");
        }

        $actual = hash_file('sha256', $path);

        if (! hash_equals($expected, (string) $actual)) {
            throw new RuntimeException("Empreinte invalide pour {$label} (archive corrompue ou altérée)");
        }
    }
}
