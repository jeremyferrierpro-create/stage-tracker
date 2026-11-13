<?php

namespace App\Domain\Journal\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Internship\Models\Internship;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\DailyLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Journal quotidien (T-027/T-028, MLD §8).
 */
class DailyLog extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected static function newFactory(): DailyLogFactory
    {
        return DailyLogFactory::new();
    }

    public const STATUSES = ['draft', 'closed'];

    public const STATUS_LABELS = [
        'draft' => 'Brouillon',
        'closed' => 'Clôturé',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'internship_id',
        'workspace_id',
        'author_id',
        'work_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'objectives',
        'work_done',
        'difficulties',
        'solutions',
        'results',
        'remaining',
        'private_notes',
        'status',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'closed_at' => 'datetime',
        ];
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return HasMany<DailyLogActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(DailyLogActivity::class);
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
