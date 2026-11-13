<?php

namespace App\Domain\Training\Models;

use App\Domain\Identity\Models\Workspace;
use App\Domain\Organization\Models\Organization;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\TrainingProgramFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Programme de formation (T-020, MLD §4).
 * Toujours rattaché à une organisation de son propre workspace (FK composite).
 */
class TrainingProgram extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected static function newFactory(): TrainingProgramFactory
    {
        return TrainingProgramFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'organization_id',
        'name',
        'certification',
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
     * @return BelongsTo<Workspace, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return HasMany<TrainingEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class);
    }
}
