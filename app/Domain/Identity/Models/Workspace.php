<?php

namespace App\Domain\Identity\Models;

use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasFactory, HasUuids;

    protected static function newFactory(): WorkspaceFactory
    {
        return WorkspaceFactory::new();
    }

    public const TYPES = ['personal', 'association', 'professional', 'pedagogical'];

    public const ROLES = ['owner', 'admin', 'trainee', 'supervisor', 'tutor', 'viewer'];

    public const TYPE_LABELS = [
        'personal' => 'Personnel',
        'association' => 'Associatif',
        'professional' => 'Professionnel',
        'pedagogical' => 'Pédagogique',
    ];

    public const ROLE_LABELS = [
        'owner' => 'Propriétaire',
        'admin' => 'Administrateur',
        'trainee' => 'Stagiaire',
        'supervisor' => 'Maître de stage',
        'tutor' => 'Tuteur',
        'viewer' => 'Lecteur',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'type', 'owner_id', 'status'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<WorkspaceMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function membershipFor(User $user): ?WorkspaceMember
    {
        return $this->members()->where('user_id', $user->id)->first();
    }

    /**
     * Rôle du membre actif, ou null si l'utilisateur n'est pas membre actif.
     */
    public function roleFor(User $user): ?string
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->value('role');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public static function roleLabel(string $role): string
    {
        return self::ROLE_LABELS[$role] ?? $role;
    }
}
