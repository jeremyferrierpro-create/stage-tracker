<?php

namespace App\Domain\Evidence\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Project\Models\Activity;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\EvidenceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Preuve (T-041, MLD §10). Une preuve est soit un fichier stocké
 * (storage_key), soit un lien externe (external_url).
 */
class Evidence extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    /**
     * Le pluralisateur traite « evidence » comme invariable : table explicite.
     */
    protected $table = 'evidences';

    protected static function newFactory(): EvidenceFactory
    {
        return EvidenceFactory::new();
    }

    public const TYPES = [
        'screenshot', 'mockup', 'uml', 'mcd', 'mld', 'test_report',
        'lighthouse', 'accessibility', 'technical_doc', 'commit',
        'pull_request', 'external_link',
    ];

    public const TYPE_LABELS = [
        'screenshot' => 'Capture d\'écran',
        'mockup' => 'Maquette',
        'uml' => 'Diagramme UML',
        'mcd' => 'MCD',
        'mld' => 'MLD',
        'test_report' => 'Rapport de test',
        'lighthouse' => 'Rapport Lighthouse',
        'accessibility' => 'Audit d\'accessibilité',
        'technical_doc' => 'Document technique',
        'commit' => 'Commit',
        'pull_request' => 'Pull request',
        'external_link' => 'Lien externe',
    ];

    public const VISIBILITIES = ['private', 'stage', 'shared'];

    public const VISIBILITY_LABELS = [
        'private' => 'Privée (moi uniquement)',
        'stage' => 'Stage (participants du stage)',
        'shared' => 'Partagée (tous les membres de l\'espace)',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'workspace_id',
        'owner_id',
        'title',
        'type',
        'description',
        'demonstrated',
        'storage_key',
        'external_url',
        'visibility',
        'captured_at',
        'archived_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<Activity, $this>
     */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(
            Activity::class,
            'activity_evidences',
            'evidence_id',
            'activity_id',
        )->withPivot(['note', 'workspace_id'])->withTimestamps();
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function visibilityLabel(): string
    {
        return self::VISIBILITY_LABELS[$this->visibility] ?? $this->visibility;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
