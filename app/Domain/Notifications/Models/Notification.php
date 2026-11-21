<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notification in-app persistante (REQ-F-002) : alimente la cloche,
 * les toasts et la page notifications.
 */
class Notification extends Model
{
    use BelongsToWorkspace, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'workspace_id',
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
