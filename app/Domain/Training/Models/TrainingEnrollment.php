<?php

namespace App\Domain\Training\Models;

use App\Domain\Identity\Models\User;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\TrainingEnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inscription d'un utilisateur à un programme de formation (T-020, MLD §4).
 * Une seule inscription par (utilisateur, programme) : UNIQUE en base.
 */
class TrainingEnrollment extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected static function newFactory(): TrainingEnrollmentFactory
    {
        return TrainingEnrollmentFactory::new();
    }

    public const STATUSES = ['active', 'completed', 'withdrawn'];

    public const STATUS_LABELS = [
        'active' => 'En cours',
        'completed' => 'Terminée',
        'withdrawn' => 'Abandon',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'training_program_id',
        'workspace_id',
        'start_date',
        'end_date',
        'status',
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
     * @return BelongsTo<TrainingProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
