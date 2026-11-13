<?php

namespace App\Domain\Project\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tâche Kanban (T-026, MLD §7). Peut être rattachée à une activité.
 */
class Task extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }

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
        'activity_id',
        'workspace_id',
        'title',
        'description',
        'priority',
        'status',
        'due_date',
        'acceptance_criteria',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function priorityLabel(): string
    {
        return self::PRIORITY_LABELS[$this->priority] ?? $this->priority;
    }
}
