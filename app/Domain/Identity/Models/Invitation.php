<?php

namespace App\Domain\Identity\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Invitation à rejoindre un workspace (MLD §2, APPFLOW §6.1).
 *
 * Seul le hash du token est stocké : le token en clair n'existe que dans
 * l'e-mail envoyé à l'invité.
 */
class Invitation extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected static function newFactory(): InvitationFactory
    {
        return InvitationFactory::new();
    }

    /**
     * Rôles qu'un propriétaire peut proposer (le rôle `owner` n'est jamais
     * proposé : il reste unique et lié au créateur de l'espace).
     *
     * @var list<string>
     */
    public const OWNER_ASSIGNABLE_ROLES = ['admin', 'trainee', 'supervisor', 'tutor', 'viewer'];

    /**
     * Rôles qu'un administrateur peut proposer (pas de promotion au niveau
     * administrateur par un pair).
     *
     * @var list<string>
     */
    public const ADMIN_ASSIGNABLE_ROLES = ['trainee', 'supervisor', 'tutor', 'viewer'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'email',
        'role',
        'token_hash',
        'expires_at',
        'accepted_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value === null ? null : Str::lower(trim($value));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<Invitation>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }
}
