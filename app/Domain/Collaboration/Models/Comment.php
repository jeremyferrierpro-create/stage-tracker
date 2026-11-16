<?php

namespace App\Domain\Collaboration\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Project\Models\Activity;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Commentaire d'activité (T-050, MLD §12). Auteur, date et contenu sont
 * immuables (INV-12) : le modèle n'écrit jamais updated_at et les
 * contrôleurs n'exposent aucune édition.
 */
class Comment extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected static function newFactory(): CommentFactory
    {
        return CommentFactory::new();
    }

    public $timestamps = false;

    public const VISIBILITIES = ['private', 'stage', 'shared'];

    public const VISIBILITY_LABELS = [
        'private' => 'Privé (moi uniquement)',
        'stage' => 'Stage (participants du stage)',
        'shared' => 'Partagée (tous les membres de l\'espace)',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'activity_id',
        'workspace_id',
        'author_id',
        'content',
        'visibility',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function visibilityLabel(): string
    {
        return self::VISIBILITY_LABELS[$this->visibility] ?? $this->visibility;
    }
}
