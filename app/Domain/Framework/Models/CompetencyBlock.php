<?php

namespace App\Domain\Framework\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bloc de compétences d'une version (T-021, MLD §5).
 */
class CompetencyBlock extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = ['framework_version_id', 'code', 'title', 'description', 'position'];

    /**
     * @return array<string, int>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<FrameworkVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(FrameworkVersion::class, 'framework_version_id');
    }
}
