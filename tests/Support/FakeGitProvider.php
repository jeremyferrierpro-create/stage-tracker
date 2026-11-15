<?php

namespace Tests\Support;

use App\Integrations\Git\GitProviderInterface;
use Throwable;

/**
 * Fournisseur Git factice pour les tests (pas un cas de test PHPUnit :
 * le nom du fichier évite que Pint applique php_unit_method_casing aux
 * méthodes de l'interface, notamment testConnection()).
 */
class FakeGitProvider implements GitProviderInterface
{
    /**
     * @param  list<array<string, mixed>>  $commits
     */
    public function __construct(
        private readonly array $commits = [],
        private readonly ?Throwable $throw = null,
    ) {}

    public function testConnection(string $repositoryUrl, ?string $token): array
    {
        if ($this->throw !== null) {
            throw $this->throw;
        }

        return ['name' => 'demo/repo', 'default_branch' => 'main'];
    }

    public function fetchCommits(string $repositoryUrl, ?string $token, string $branch, int $page, int $perPage): array
    {
        if ($this->throw !== null) {
            throw $this->throw;
        }

        return $page === 1 ? $this->commits : [];
    }
}
