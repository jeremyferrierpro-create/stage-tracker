<?php

namespace App\Domain\Internship\Models;

use App\Domain\Identity\Models\User;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Participant d'un stage (T-023, MLD §6).
 */
class InternshipParticipant extends Model
{
    use BelongsToWorkspace;

    public const ROLES = ['trainee', 'supervisor', 'tutor', 'observer'];

    public const ROLE_LABELS = [
        'trainee' => 'Stagiaire',
        'supervisor' => 'Maître de stage',
        'tutor' => 'Tuteur pédagogique',
        'observer' => 'Observateur (lecture)',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'internship_id',
        'workspace_id',
        'user_id',
        'role',
        'permissions',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->role;
    }
}
