<?php

namespace App\Domain\Framework\Models;

use Database\Factories\CompetencyFrameworkFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Référentiel de compétences, ressource GLOBALE multi-formations
 * (T-021, MLD §5, DEC-ST-003). Aucun référentiel codé en dur.
 *
 * Administration : propriétaires / administrateurs d'au moins un espace
 * actif (décision DEC-ST-015).
 */
class CompetencyFramework extends Model
{
    use HasFactory, HasUuids;

    protected static function newFactory(): CompetencyFrameworkFactory
    {
        return CompetencyFrameworkFactory::new();
    }

    public const STATUSES = ['draft', 'active', 'archived'];

    public const STATUS_LABELS = [
        'draft' => 'Brouillon',
        'active' => 'Actif',
        'archived' => 'Archivé',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'issuing_authority', 'description', 'status'];

    /**
     * @return HasMany<FrameworkVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(FrameworkVersion::class, 'framework_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
