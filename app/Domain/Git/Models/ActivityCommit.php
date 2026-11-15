<?php

namespace App\Domain\Git\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Project\Models\Activity;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Association commit ↔ activité (T-045, MLD §9).
 */
class ActivityCommit extends Model
{
    use BelongsToWorkspace;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'activity_id',
        'commit_id',
        'workspace_id',
        'linked_by',
        'linked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<Commit, $this>
     */
    public function commit(): BelongsTo
    {
        return $this->belongsTo(Commit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }
}
