<?php

namespace App\Jobs;

use App\Domain\Audit\AuditLogger;
use App\Domain\Git\Models\Repository;
use App\Domain\Git\Services\CommitImportService;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Synchronisation asynchrone d'un dépôt Git (Lot 1). Le worker doit tourner
 * (`php artisan queue:work`) ; sinon l'exécution peut rester en file.
 */
class SyncRepository implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly string $repositoryId,
        public readonly int $userId,
    ) {}

    public function handle(CommitImportService $importer, AuditLogger $audit): void
    {
        $repository = Repository::query()->find($this->repositoryId);

        if ($repository === null) {
            return;
        }

        $run = $importer->sync($repository);

        $workspace = Workspace::query()->find($repository->workspace_id);
        $user = User::query()->find($this->userId);

        if ($workspace !== null && $user !== null) {
            $audit->record(
                'repository.synced',
                $workspace,
                $repository,
                [
                    'repository_name' => $repository->name,
                    'status' => $run->status,
                    'items_imported' => $run->items_imported,
                ],
                $user,
            );
        }
    }
}
