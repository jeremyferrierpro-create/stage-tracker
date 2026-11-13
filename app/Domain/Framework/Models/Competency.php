<?php

namespace App\Domain\Framework\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Compétence d'une version, rattachable à un bloc et/ou à une compétence
 * parente (hiérarchie à deux niveaux, T-021, MLD §5).
 */
class Competency extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'framework_version_id',
        'block_id',
        'parent_id',
        'code',
        'title',
        'description',
        'position',
    ];

    /**
     * @return array<string, int>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<FrameworkVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(FrameworkVersion::class, 'framework_version_id');
    }

    /**
     * @return BelongsTo<CompetencyBlock, $this>
     */
    public function block(): BelongsTo
    {
        return $this->belongsTo(CompetencyBlock::class, 'block_id');
    }

    /**
     * @return BelongsTo<Competency, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<CompetencyCriterion, $this>
     */
    public function criteria(): HasMany
    {
        return $this->hasMany(CompetencyCriterion::class);
    }
}
