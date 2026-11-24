<?php

namespace App\Domain\Planning\Models;

use App\Domain\Evaluation\Models\Competency;
use App\Domain\Internship\Models\Internship;
use App\Domain\Project\Models\Activity;
use App\Domain\Project\Models\Project;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\PlanEntryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Entrée de planning prévisionnel (prévu), importée depuis un document
 * markdown. Comparée aux daily_logs (réel) par PlanningComparisonService.
 */
class PlanEntry extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected static function newFactory(): PlanEntryFactory
    {
        return PlanEntryFactory::new();
    }

    public const SLOTS = ['morning', 'afternoon'];

    public const SLOT_LABELS = [
        'morning' => 'Matin (9h-12h30)',
        'afternoon' => 'Après-midi (13h30-17h)',
    ];

    /**
     * Durée d'un créneau en minutes (9h-12h30 et 13h30-17h).
     */
    public const SLOT_MINUTES = 210;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'internship_id',
        'workspace_id',
        'project_id',
        'plan_date',
        'slot',
        'title',
        'planned_minutes',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Internship, $this>
     */
    public function internship(): BelongsTo
    {
        return $this->belongsTo(Internship::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Activités réelles qui réalisent cette entrée planifiée.
     *
     * @return BelongsToMany<Activity, $this>
     */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(
            Activity::class,
            'plan_entry_activities',
            'plan_entry_id',
            'activity_id',
        )->withPivot('workspace_id')->withTimestamps();
    }

    /**
     * Compétences du workspace visées par cette entrée planifiée.
     *
     * @return BelongsToMany<Competency, $this>
     */
    public function competencies(): BelongsToMany
    {
        return $this->belongsToMany(
            Competency::class,
            'plan_entry_competencies',
            'plan_entry_id',
            'competency_id',
        )->withPivot('workspace_id')->withTimestamps();
    }

    public function slotLabel(): string
    {
        return self::SLOT_LABELS[$this->slot] ?? $this->slot;
    }
}
