<?php

namespace App\Domain\Evaluation\Models;

use App\Domain\Identity\Models\Workspace;
use Database\Factories\CompetencyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Compétence définie directement par les administrateurs du workspace
 * (simplification 1.6.0 : plus de référentiel versionné, bloc ou critère).
 * Elle est rattachable à des activités (ActivityCompetency, T-060) et
 * évaluable nominativement (T-061).
 */
class Competency extends Model
{
    use HasFactory, HasUuids;

    protected static function newFactory(): CompetencyFactory
    {
        return CompetencyFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'code',
        'title',
        'description',
    ];

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return HasMany<ActivityCompetency, $this>
     */
    public function associations(): HasMany
    {
        return $this->hasMany(ActivityCompetency::class, 'competency_id');
    }
}
