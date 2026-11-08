<?php

namespace App\Domain\Audit\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Entrée du journal d'audit (MLD §14).
 *
 * Append-only : toute tentative de mise à jour ou de suppression via Eloquent
 * lève une exception. Le trigger PostgreSQL de la migration protège également
 * les requêtes directes.
 */
class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'metadata',
        'ip',
        'user_agent',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Le journal d\'audit est append-only : mise à jour interdite.');
        });

        static::deleting(function (): never {
            throw new LogicException('Le journal d\'audit est append-only : suppression interdite.');
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function subjectLabel(): string
    {
        if ($this->subject_type === null) {
            return '—';
        }

        $parts = explode('\\', $this->subject_type);

        return end($parts).($this->subject_id !== null ? ' #'.$this->subject_id : '');
    }
}
