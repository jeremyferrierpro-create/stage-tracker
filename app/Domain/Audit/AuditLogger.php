<?php

namespace App\Domain\Audit;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Service d'écriture du journal d'audit (T-009, SECURITY §3.6).
 *
 * Les métadonnées sont filtrées par liste blanche : aucun mot de passe, token,
 * secret ou donnée libre ne peut être journalisé par erreur.
 */
final class AuditLogger
{
    /**
     * Clés de métadonnées autorisées (T-009, risque « données sensibles »).
     *
     * @var list<string>
     */
    public const METADATA_ALLOWLIST = [
        'role',
        'email_hash',
        'invitation_id',
        'organization_id',
        'organization_name',
        'organization_type',
        'reason',
        'status',
        'workspace_name',
        'changed',
        'from',
        'to',
        'count',
        'training_program_id',
        'training_program_name',
        'enrollment_id',
        'framework_id',
        'framework_name',
        'version',
        'subject',
        'repository_name',
        'commit_sha',
        'items_imported',
        'competency_code',
    ];

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $action,
        ?Workspace $workspace = null,
        ?Model $subject = null,
        array $metadata = [],
        ?User $actor = null,
    ): AuditLog {
        $request = app()->bound('request') ? request() : null;
        $userAgent = $request?->userAgent();

        return AuditLog::create([
            'workspace_id' => $workspace?->getKey(),
            'actor_id' => $actor?->getKey() ?? Auth::id(),
            'action' => Str::limit($action, 80, ''),
            'subject_type' => $subject === null ? null : $subject::class,
            'subject_id' => $subject?->getKey(),
            'metadata' => $this->sanitize($metadata),
            'ip' => $request?->ip(),
            'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 500, ''),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, scalar|null>
     */
    public function sanitize(array $metadata): array
    {
        $safe = [];

        foreach ($metadata as $key => $value) {
            if (! is_string($key) || ! in_array($key, self::METADATA_ALLOWLIST, true)) {
                continue;
            }

            if ($value === null || is_scalar($value)) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }
}
