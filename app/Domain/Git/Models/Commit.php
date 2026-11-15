<?php

namespace App\Domain\Git\Models;

use App\Domain\Project\Models\Activity;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\CommitFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Commit importé depuis un dépôt (T-044, MLD §9). INV-05 : unicité
 * (repository_id, sha).
 */
class Commit extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected static function newFactory(): CommitFactory
    {
        return CommitFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'repository_id',
        'workspace_id',
        'sha',
        'author_name',
        'author_email',
        'github_author',
        'committed_at',
        'message',
        'branch',
        'url',
        'imported_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'committed_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * @return HasMany<CommitFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(CommitFile::class);
    }

    /**
     * @return BelongsToMany<ActivityCommit, $this>
     */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(
            Activity::class,
            'activity_commits',
            'commit_id',
            'activity_id',
        )->withPivot(['linked_by', 'linked_at', 'workspace_id']);
    }

    public function shortSha(): string
    {
        return mb_substr($this->sha, 0, 7);
    }
}
