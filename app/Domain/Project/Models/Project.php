<?php

namespace App\Domain\Project\Models;

use App\Domain\Internship\Models\Internship;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Projet d'un stage (T-024, MLD §7).
 */
class Project extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    public const STATUSES = ['active', 'completed', 'archived'];

    public const STATUS_LABELS = [
        'active' => 'En cours',
        'completed' => 'Terminé',
        'archived' => 'Archivé',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'internship_id',
        'workspace_id',
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
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
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
