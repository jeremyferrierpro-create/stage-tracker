<?php

namespace App\Domain\Organization\Models;

use App\Domain\Identity\Models\User;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\OrganizationMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rattachement d'un utilisateur à une organisation (MLD §3, FR-3.3).
 */
class OrganizationMember extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected static function newFactory(): OrganizationMemberFactory
    {
        return OrganizationMemberFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = ['organization_id', 'workspace_id', 'user_id', 'role'];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
