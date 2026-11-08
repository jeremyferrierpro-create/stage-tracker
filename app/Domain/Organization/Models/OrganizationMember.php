<?php

namespace App\Domain\Organization\Models;

use App\Domain\Identity\Models\User;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rattachement d'un utilisateur à une organisation (MLD §3, FR-3.3).
 */
class OrganizationMember extends Model
{
    use BelongsToWorkspace;

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
