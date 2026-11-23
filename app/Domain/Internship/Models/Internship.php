<?php

namespace App\Domain\Internship\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Journal\Models\DailyLog;
use App\Domain\Planning\Models\PlanEntry;
use App\Domain\Project\Models\Project;
use App\Domain\Reporting\Models\Report;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\InternshipFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stage (T-023, MLD). Rattaché à un stagiaire et à l'entreprise
 * d'accueil (champ texte, simplification 1.6.0).
 */
class Internship extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected static function newFactory(): InternshipFactory
    {
        return InternshipFactory::new();
    }

    public const STATUSES = ['draft', 'active', 'completed', 'archived'];

    public const STATUS_LABELS = [
        'draft' => 'Brouillon',
        'active' => 'En cours',
        'completed' => 'Terminé',
        'archived' => 'Archivé',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'trainee_id',
        'company_name',
        'title',
        'start_date',
        'end_date',
        'objectives',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function trainee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainee_id');
    }

    /**
     * @return HasMany<InternshipParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(InternshipParticipant::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return HasMany<DailyLog, $this>
     */
    public function dailyLogs(): HasMany
    {
        return $this->hasMany(DailyLog::class);
    }

    /**
     * Planning prévisionnel importé (prévu vs réel).
     *
     * @return HasMany<PlanEntry, $this>
     */
    public function planEntries(): HasMany
    {
        return $this->hasMany(PlanEntry::class);
    }

    /**
     * Rapports générés pour ce stage (T-063).
     *
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
