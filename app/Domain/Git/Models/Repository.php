<?php

namespace App\Domain\Git\Models;

use App\Domain\Project\Models\Project;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\RepositoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dépôt Git associé à un projet (T-043, MLD §9). Le jeton est chiffré côté
 * serveur (`token_encrypted`) et jamais exposé (attribut masqué + absent
 * des vues).
 */
class Repository extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected static function newFactory(): RepositoryFactory
    {
        return RepositoryFactory::new();
    }

    public const PROVIDERS = ['github', 'gitlab', 'other'];

    /**
     * Le jeton chiffré n'est jamais sérialisé ni affiché.
     *
     * @var list<string>
     */
    protected $hidden = ['token_encrypted'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'project_id',
        'workspace_id',
        'provider',
        'external_id',
        'name',
        'repository_url',
        'default_branch',
        'last_synced_at',
        'token_encrypted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<Commit, $this>
     */
    public function commits(): HasMany
    {
        return $this->hasMany(Commit::class);
    }

    /**
     * @return HasMany<SyncRun, $this>
     */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class)->orderByDesc('started_at');
    }

    public function hasToken(): bool
    {
        return $this->token_encrypted !== null;
    }
}
