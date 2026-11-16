<?php

namespace App\Domain\Collaboration\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Project\Models\Activity;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Revue d'activité (T-051, MLD §12). La demande est immuable (created_at) ;
 * le statut suit des transitions contrôlées (voir ReviewService).
 */
class Review extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected static function newFactory(): ReviewFactory
    {
        return ReviewFactory::new();
    }

    public $timestamps = false;

    public const STATUSES = ['requested', 'in_progress', 'approved', 'changes_requested'];

    public const STATUS_LABELS = [
        'requested' => 'Demandée',
        'in_progress' => 'En cours',
        'approved' => 'Approuvée',
        'changes_requested' => 'Modifications demandées',
    ];

    /**
     * Transitions autorisées (T-051, « statuts cohérents »).
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'requested' => ['in_progress', 'approved', 'changes_requested'],
        'in_progress' => ['approved', 'changes_requested'],
        'changes_requested' => ['in_progress', 'approved'],
        'approved' => [],
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'activity_id',
        'workspace_id',
        'reviewer_id',
        'status',
        'feedback',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function canTransitionTo(string $next): bool
    {
        return in_array($next, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
