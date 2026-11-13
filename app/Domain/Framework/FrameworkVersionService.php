<?php

namespace App\Domain\Framework;

use App\Domain\Audit\AuditLogger;
use App\Domain\Framework\Models\CompetencyFramework;
use App\Domain\Framework\Models\FrameworkVersion;
use App\Domain\Identity\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Publication d'une version de référentiel (T-022, INV-08).
 *
 * La publication est une opération unique et définitive : elle fixe
 * `published_at` et bascule le statut en `published` dans une seule
 * instruction SQL (garde sur `status = 'draft'`). Après publication, la
 * version et son contenu sont immuables (trigger PostgreSQL).
 */
final class FrameworkVersionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return FrameworkVersion la version publiée
     *
     * @throws UniqueConstraintViolationException aucune réexécution de publication
     */
    public function publish(FrameworkVersion $version, ?User $actor = null): FrameworkVersion
    {
        return DB::transaction(function () use ($version, $actor): FrameworkVersion {
            $updated = FrameworkVersion::query()
                ->whereKey($version->getKey())
                ->where('status', 'draft')
                ->update([
                    'status' => 'published',
                    'published_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                throw new \RuntimeException(
                    'La version est déjà publiée ou archivée : publication impossible.'
                );
            }

            $published = FrameworkVersion::query()->findOrFail($version->getKey());

            $this->audit->record(
                'framework.published',
                null,
                $published,
                [
                    'framework_id' => $published->framework_id,
                    'framework_name' => $published->framework->name,
                    'version' => $published->version,
                    'status' => 'published',
                ],
                $actor ?? null,
            );

            return $published;
        });
    }

    /**
     * Garde applicatif : refuse toute écriture de contenu sur une version
     * publiée. Le trigger PostgreSQL est le second niveau de défense.
     */
    public function assertMutable(FrameworkVersion $version): void
    {
        if ($version->isPublished()) {
            throw new \RuntimeException(
                'Version publiée : le contenu est immuable (INV-08).'
            );
        }
    }

    public function frameworkOf(FrameworkVersion $version): CompetencyFramework
    {
        return $version->framework;
    }
}
