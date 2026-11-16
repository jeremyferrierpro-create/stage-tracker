<?php

namespace App\Domain\Reporting\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Internship\Models\Internship;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rapport généré (T-063, MLD §13) : contenu filtré par visibilité (INV-14),
 * produit par un job asynchrone (statut queued → generated|failed) et
 * stocké sur le disque privé.
 */
class Report extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'internship_id',
        'workspace_id',
        'template_version_id',
        'generated_by',
        'generated_at',
        'file_path',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    public const STATUSES = ['queued', 'generated', 'failed'];

    /**
     * @return BelongsTo<Internship, $this>
     */
    public function internship(): BelongsTo
    {
        return $this->belongsTo(Internship::class);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<ReportTemplateVersion, $this>
     */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(ReportTemplateVersion::class, 'template_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'queued' => 'En file',
            'generated' => 'Généré',
            'failed' => 'Échec',
            default => $this->status,
        };
    }
}
