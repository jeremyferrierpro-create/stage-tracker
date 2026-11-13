<?php

namespace App\Domain\Framework\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Critère évaluable d'une compétence (T-021, MLD §5).
 */
class CompetencyCriterion extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = ['competency_id', 'label', 'description', 'position'];

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
     * @return BelongsTo<Competency, $this>
     */
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }
}
