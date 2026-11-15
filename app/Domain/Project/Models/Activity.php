<?php

namespace App\Domain\Project\Models;

use App\Domain\Evidence\Models\Evidence;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Activité technique (T-025, MLD §7).
 */
class Activity extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected static function newFactory(): ActivityFactory
    {
        return ActivityFactory::new();
    }

    public const TYPES = ['feature', 'fix', 'study', 'design', 'research', 'maintenance', 'improvement'];

    public const TYPE_LABELS = [
        'feature' => 'Fonctionnalité',
        'fix' => 'Correction',
        'study' => 'Étude',
        'design' => 'Conception',
        'research' => 'Recherche',
        'maintenance' => 'Maintenance',
        'improvement' => 'Amélioration',
    ];

    public const STATUSES = ['todo', 'in_progress', 'to_review', 'done', 'blocked'];

    public const STATUS_LABELS = [
        'todo' => 'À faire',
        'in_progress' => 'En cours',
        'to_review' => 'À relire',
        'done' => 'Terminée',
        'blocked' => 'Bloquée',
    ];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public const PRIORITY_LABELS = [
        'low' => 'Basse',
        'medium' => 'Moyenne',
        'high' => 'Haute',
        'critical' => 'Critique',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'workspace_id',
        'title',
        'description',
        'type',
        'status',
        'priority',
        'start_date',
        'end_date',
        'objectives',
        'results',
        'difficulties',
        'solutions',
        'archived_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function priorityLabel(): string
    {
        return self::PRIORITY_LABELS[$this->priority] ?? $this->priority;
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return BelongsToMany<Evidence, $this>
     */
    public function evidences(): BelongsToMany
    {
        return $this->belongsToMany(
            Evidence::class,
            'activity_evidences',
            'activity_id',
            'evidence_id',
        )->withPivot(['note', 'workspace_id'])->withTimestamps();
    }
}
