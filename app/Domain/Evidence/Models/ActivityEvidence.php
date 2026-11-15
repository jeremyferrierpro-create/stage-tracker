<?php

namespace App\Domain\Evidence\Models;

use App\Domain\Project\Models\Activity;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lien activité ↔ preuve (T-042, MLD §10). Une preuve peut documenter
 * plusieurs activités (N-N, doublons interdits par contrainte unique).
 */
class ActivityEvidence extends Model
{
    use BelongsToWorkspace;

    /**
     * Le pluralisateur traite « evidence » comme invariable : table explicite.
     */
    protected $table = 'activity_evidences';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'activity_id',
        'evidence_id',
        'workspace_id',
        'note',
    ];

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<Evidence, $this>
     */
    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }
}
