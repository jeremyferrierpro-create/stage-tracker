<?php

namespace App\Integrations\Git;

/**
 * Abstraction du connecteur Git (T-043, DEC-ST-007). Toute implémentation
 * travaille à partir de l'URL du dépôt et du jeton en clair à l'appel ; le
 * jeton ne doit jamais apparaître dans une réponse HTTP ni dans un message
 * d'erreur.
 */
interface GitProviderInterface
{
    /**
     * Vérifie l'accès au dépôt. Retourne un tableau d'informations
     * (nom, branche par défaut) ou lève une RuntimeException en cas
     * d'échec (dépôt inaccessible, jeton invalide, limite d'API).
     *
     * @return array<string, mixed>
     */
    public function testConnection(string $repositoryUrl, ?string $token): array;

    /**
     * Récupère une page de commits.
     *
     * @return list<array<string, mixed>> Chaque entrée : sha, message,
     *                                    author_name, author_email, github_author, committed_at (string
     *                                    ISO 8601), url, branch, files (list de path/change_type/
     *                                    additions/deletions — peut être vide si le fournisseur ne
     *                                    retourne pas les fichiers dans la liste).
     */
    public function fetchCommits(string $repositoryUrl, ?string $token, string $branch, int $page, int $perPage, ?string $since = null): array;

    /**
     * Récupère les fichiers modifiés par un commit (GitHub : endpoint
     * /repos/{owner}/{repo}/commits/{sha}).
     *
     * @return list<array<string, mixed>> path, change_type, additions, deletions
     */
    public function fetchCommitFiles(string $repositoryUrl, ?string $token, string $sha): array;
}
