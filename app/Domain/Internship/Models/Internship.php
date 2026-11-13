<?php

namespace App\Domain\Internship\Models;

use App\Domain\Framework\Models\FrameworkVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Project\Models\Project;
use App\Domain\Training\Models\TrainingProgram;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\InternshipFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stage (T-023, MLD §6). Rattaché à un stagiaire, une organisation
 * d'accueil et une version de référentiel (publiée).
 */
class Internship extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected static function newFactory(): InternshipFactory
    {
        return InternshipFactory::new();
    }

    public const STATUSES = ['draft', 'active', 'completed', 'archived'];

    public const STATUS_LABELS = [
        'draft' => 'Brouillon',
        'active' => 'En cours',
        'completed' => 'Terminé',
        'archived' => 'Archivé',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'trainee_id',
        'host_organization_id',
        'training_program_id',
        'framework_version_id',
        'title',
        'start_date',
        'end_date',
        'objectives',
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
     * @return BelongsTo<User, $this>
     */
    public function trainee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainee_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function hostOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'host_organization_id');
    }

    /**
     * @return BelongsTo<TrainingProgram, $this>
     */
    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    /**
     * @return BelongsTo<FrameworkVersion, $this>
     */
    public function frameworkVersion(): BelongsTo
    {
        return $this->belongsTo(FrameworkVersion::class, 'framework_version_id');
    }

    /**
     * @return HasMany<InternshipParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(InternshipParticipant::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
