<?php

namespace App\Domain\Framework\Models;

use Database\Factories\FrameworkVersionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Version d'un référentiel (T-021, MLD §5).
 * Une fois `published` (via FrameworkVersionService), la version est
 * immuable : aucun UPDATE/DELETE applicatif ni SQL (trigger T-022, INV-08).
 */
class FrameworkVersion extends Model
{
    use HasFactory, HasUuids;

    protected static function newFactory(): FrameworkVersionFactory
    {
        return FrameworkVersionFactory::new();
    }

    public const STATUSES = ['draft', 'published', 'archived'];

    public const STATUS_LABELS = [
        'draft' => 'Brouillon',
        'published' => 'Publiée',
        'archived' => 'Archivée',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'framework_id',
        'version',
        'effective_date',
        'status',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CompetencyFramework, $this>
     */
    public function framework(): BelongsTo
    {
        return $this->belongsTo(CompetencyFramework::class, 'framework_id');
    }

    /**
     * @return HasMany<CompetencyBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(CompetencyBlock::class);
    }

    /**
     * @return HasMany<Competency, $this>
     */
    public function competencies(): HasMany
    {
        return $this->hasMany(Competency::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
