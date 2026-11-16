<?php

namespace App\Domain\Evaluation\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Évaluation nominative d'une compétence (T-061, MLD §11) : auteur
 * (assessor_id), date (assessed_at) et appréciation conservés —
 * provenance traçable (AC-11.2). Une évaluation `validated` portera la
 * compétence à `evaluated` ; c'est l'unique chemin vers ce statut (INV-09).
 */
class CompetencyAssessment extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'activity_competency_id',
        'assessor_id',
        'appreciation',
        'status',
        'assessed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessed_at' => 'datetime',
        ];
    }

    public const STATUSES = ['pending', 'validated', 'rejected'];

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        'pending' => 'En attente',
        'validated' => 'Validée',
        'rejected' => 'Rejetée',
    ];

    /**
     * @return BelongsTo<ActivityCompetency, $this>
     */
    public function activityCompetency(): BelongsTo
    {
        return $this->belongsTo(ActivityCompetency::class, 'activity_competency_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
