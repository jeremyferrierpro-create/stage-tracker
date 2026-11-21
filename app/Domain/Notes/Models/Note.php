<?php

namespace App\Domain\Notes\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Internship\Models\Internship;
use App\Domain\Project\Models\Project;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pense-bête (REQ-F-004) : note personnelle ou partagée dans l'espace,
 * avec échéance, rappel et rattachement optionnel stage / projet.
 */
class Note extends Model
{
    use BelongsToWorkspace, HasUuids;

    public const SCOPES = ['personal', 'workspace'];

    public const SCOPE_LABELS = [
        'personal' => 'Personnelle',
        'workspace' => 'Partagée dans l\'espace',
    ];

    public const STATUSES = ['todo', 'done'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'workspace_id',
        'user_id',
        'title',
        'body',
        'scope',
        'status',
        'due_at',
        'remind_at',
        'reminded_at',
        'project_id',
        'internship_id',
        'done_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'remind_at' => 'datetime',
            'reminded_at' => 'datetime',
            'done_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Internship, $this>
     */
    public function internship(): BelongsTo
    {
        return $this->belongsTo(Internship::class);
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isOverdue(): bool
    {
        return ! $this->isDone()
            && $this->due_at !== null
            && $this->due_at->isPast();
    }

    public function scopeLabel(): string
    {
        return self::SCOPE_LABELS[$this->scope] ?? $this->scope;
    }
}
