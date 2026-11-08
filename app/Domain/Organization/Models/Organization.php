<?php

namespace App\Domain\Organization\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Organisation d'accueil ou organisme de formation (MLD §3, FR-3.1).
 * Suppression logique par `archived_at` : aucun effacement physique.
 */
class Organization extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }

    /**
     * @var list<string>
     */
    public const TYPES = ['company', 'association', 'training_center', 'partner'];

    public const TYPE_LABELS = [
        'company' => 'Entreprise d\'accueil',
        'association' => 'Association',
        'training_center' => 'Organisme de formation',
        'partner' => 'Partenaire',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'name',
        'type',
        'contact_email',
        'contact_phone',
        'address',
        'archived_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Organization>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<Organization>  $query
     */
    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    /**
     * @return HasMany<OrganizationMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public static function typeLabelFor(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? $type;
    }
}
