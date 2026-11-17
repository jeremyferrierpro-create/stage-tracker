<?php

namespace App\Domain\Evaluation\Models;

use App\Domain\Project\Models\Activity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Association activité ↔ compétence (T-060, MLD §11) : justification
 * obligatoire, statut `mobilized` à la création. Aucune transition
 * automatique (INV-09) : seul un évaluateur habilité passe une compétence
 * à `evaluated` (T-061).
 */
class ActivityCompetency extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'activity_id',
        'workspace_id',
        'competency_id',
        'justification',
        'status',
        'documented_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'documented_at' => 'datetime',
        ];
    }

    public const STATUSES = ['mobilized', 'documented', 'evaluated'];

    /**
     * Libellés UI explicites (risque T-060 : statuts confondus).
     *
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        'mobilized' => 'Mobilisée',
        'documented' => 'Documentée',
        'evaluated' => 'Évaluée',
    ];

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<Competency, $this>
     */
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    /**
     * @return HasMany<CompetencyAssessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(CompetencyAssessment::class, 'activity_competency_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Dernière appréciation nominative (T-061), la plus récente d'abord.
     */
    public function latestAssessment(): ?CompetencyAssessment
    {
        return $this->assessments()->orderByDesc('assessed_at')->first();
    }
}
