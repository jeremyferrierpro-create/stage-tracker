<?php

namespace App\Domain\Git\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historique de synchronisation (T-044, MLD §9).
 */
class SyncRun extends Model
{
    use HasUuids;

    public $timestamps = false;

    public const STATUSES = ['running', 'success', 'partial', 'failed'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'repository_id',
        'provider',
        'started_at',
        'completed_at',
        'status',
        'items_imported',
        'error_summary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
