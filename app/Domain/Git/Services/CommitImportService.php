<?php

namespace App\Domain\Git\Services;

use App\Domain\Git\Models\Commit;
use App\Domain\Git\Models\CommitFile;
use App\Domain\Git\Models\Repository;
use App\Domain\Git\Models\SyncRun;
use App\Integrations\Git\GitProviderInterface;
use Illuminate\Support\Facades\DB;

/**
 * Import idempotent des commits (T-044/T-045). Upsert sur
 * (repository_id, sha) — INV-05 : une seconde synchronisation ne crée aucun
 * doublon, ne remonte pas `imported_at` et conserve les associations
 * commit ↔ activité. Chaque exécution est tracée dans `sync_runs`.
 */
class CommitImportService
{
    private const PER_PAGE = 30;

    public function __construct(private readonly GitProviderInterface $provider) {}

    /**
     * @return SyncRun l'exécution tracée (status success/partial/failed).
     */
    public function sync(Repository $repository, int $maxPages = 3, bool $withFiles = true): SyncRun
    {
        $token = $repository->token_encrypted !== null
            ? decrypt($repository->token_encrypted)
            : null;

        $run = SyncRun::query()->create([
            'repository_id' => $repository->getKey(),
            'provider' => $repository->provider,
            'started_at' => now(),
            'status' => 'running',
            'items_imported' => 0,
        ]);

        $imported = 0;
        $errors = [];

        try {
            foreach (range(1, $maxPages) as $page) {
                $commits = $this->provider->fetchCommits(
                    $repository->repository_url,
                    $token,
                    $repository->default_branch ?? 'main',
                    $page,
                    self::PER_PAGE,
                );

                if ($commits === []) {
                    break;
                }

                foreach ($commits as $data) {
                    $imported += $this->upsertCommit($repository, $data, $withFiles);
                }
            }

            $repository->forceFill(['last_synced_at' => now()])->save();

            $run->forceFill([
                'status' => $errors === [] ? 'success' : 'partial',
                'completed_at' => now(),
                'items_imported' => $imported,
                'error_summary' => $errors === [] ? null : implode("\n", $errors),
            ])->save();
        } catch (\Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'completed_at' => now(),
                'items_imported' => $imported,
                'error_summary' => $exception->getMessage(),
            ])->save();
        }

        return $run->refresh();
    }

    /**
     * Insère ou met à jour un commit ; retourne 1 si créé, 0 sinon.
     * `imported_at` n'est écrit qu'à la création (provenance conservée).
     *
     * @param  array<string, mixed>  $data
     */
    private function upsertCommit(Repository $repository, array $data, bool $withFiles): int
    {
        $commit = Commit::query()->firstOrNew([
            'repository_id' => $repository->getKey(),
            'sha' => $data['sha'],
        ]);
        $isNew = ! $commit->exists;

        $commit->fill([
            'workspace_id' => $repository->workspace_id,
            'author_name' => $data['author_name'] ?? null,
            'author_email' => $data['author_email'] ?? null,
            'github_author' => $data['github_author'] ?? null,
            'committed_at' => $data['committed_at'],
            'message' => $data['message'],
            'branch' => $data['branch'] ?? null,
            'url' => $data['url'] ?? null,
        ]);

        if ($isNew) {
            $commit->imported_at = now();
        }

        $commit->save();

        if ($withFiles && ! empty($data['files'])) {
            $this->upsertFiles($commit, $data['files']);
        }

        return $isNew ? 1 : 0;
    }

    /**
     * Upsert des fichiers d'un commit (unicité commit_id + path).
     *
     * @param  list<array<string, mixed>>  $files
     */
    private function upsertFiles(Commit $commit, array $files): void
    {
        DB::transaction(function () use ($commit, $files): void {
            foreach ($files as $file) {
                $attributes = [
                    'commit_id' => $commit->getKey(),
                    'path' => $file['path'],
                    'workspace_id' => $commit->workspace_id,
                    'change_type' => $file['change_type'] ?? 'modified',
                    'additions' => $file['additions'] ?? null,
                    'deletions' => $file['deletions'] ?? null,
                ];

                CommitFile::query()->upsert(
                    [$attributes],
                    ['commit_id', 'path'],
                    ['change_type', 'additions', 'deletions', 'workspace_id'],
                );
            }
        });
    }
}
