<?php

namespace Tests\Support;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * Modèle de test monté sur workspace_members pour vérifier le scope global
 * BelongsToWorkspace sans dépendre d'une table métier future.
 */
class ScopedWorkspaceMember extends Model
{
    use BelongsToWorkspace;

    protected $table = 'workspace_members';

    protected $guarded = [];
}
