<?php

namespace App\Domain\Git\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fichier modifié par un commit (T-045, MLD §9).
 */
class CommitFile extends Model
{
    use BelongsToWorkspace;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'commit_id',
        'workspace_id',
        'path',
        'change_type',
        'additions',
        'deletions',
    ];

    /**
     * @return BelongsTo<Commit, $this>
     */
    public function commit(): BelongsTo
    {
        return $this->belongsTo(Commit::class);
    }
}
