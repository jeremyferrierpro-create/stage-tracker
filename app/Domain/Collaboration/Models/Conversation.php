<?php

namespace App\Domain\Collaboration\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Internship\Models\Internship;
use App\Domain\Project\Models\Project;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conversation de messagerie interne (REQ-F-003) : directe, groupe,
 * ou fil rattaché à un projet / stage.
 */
class Conversation extends Model
{
    use BelongsToWorkspace, HasUuids;

    public const TYPES = ['direct', 'group', 'project', 'internship'];

    public const TYPE_LABELS = [
        'direct' => 'Message direct',
        'group' => 'Groupe',
        'project' => 'Projet',
        'internship' => 'Stage',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'workspace_id',
        'type',
        'title',
        'project_id',
        'internship_id',
        'direct_key',
        'created_by',
    ];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function displayTitle(): string
    {
        return $this->title
            ?? $this->project?->name
            ?? $this->internship?->title
            ?? $this->typeLabel();
    }
}
