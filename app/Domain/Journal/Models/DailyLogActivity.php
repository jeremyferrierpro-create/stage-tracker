<?php

namespace App\Domain\Journal\Models;

use App\Domain\Project\Models\Activity;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Activité liée à une journée de journal (T-027, MLD §8).
 */
class DailyLogActivity extends Model
{
    use BelongsToWorkspace;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'daily_log_id',
        'activity_id',
        'workspace_id',
        'contribution',
    ];

    /**
     * @return BelongsTo<DailyLog, $this>
     */
    public function dailyLog(): BelongsTo
    {
        return $this->belongsTo(DailyLog::class);
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
