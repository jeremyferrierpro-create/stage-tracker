<?php

namespace App\Models\Concerns;

use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Couche 2 de la défense en profondeur (ADR-002) : toute requête sur un
 * modèle tenant-scopé est filtrée par le workspace courant. Le workspace_id
 * est renseigné automatiquement à la création.
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $builder): void {
            $workspace = app(WorkspaceContext::class)->get();

            if ($workspace !== null) {
                $builder->where(
                    $builder->getModel()->getTable().'.workspace_id',
                    $workspace->getKey(),
                );
            }
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('workspace_id') !== null) {
                return;
            }

            $workspace = app(WorkspaceContext::class)->get();

            if ($workspace !== null) {
                $model->setAttribute('workspace_id', $workspace->getKey());
            }
        });
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
