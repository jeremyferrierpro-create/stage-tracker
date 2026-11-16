<?php

namespace App\Domain\Reporting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Version d'un modèle de rapport (T-062, MLD §13) : trame `schema_json`
 * validée à l'écriture ; une version publiée (published_at non null) est
 * immuable et sert de base à la génération des rapports (T-063).
 */
class ReportTemplateVersion extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'template_id',
        'version',
        'schema_json',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema_json' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ReportTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'template_id');
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'template_version_id');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
