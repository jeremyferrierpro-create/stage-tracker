<?php

namespace App\Integrations\GitHub;

use App\Integrations\Git\GitProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Connecteur GitHub REST v3 (T-043). Limites d'API : 60 requêtes/heure sans
 * jeton, 5 000/heure avec jeton. En cas de 403/429 (limite), une exception
 * explicite est levée — le service d'import trace l'exécution en `partial`.
 */
class GitHubProvider implements GitProviderInterface
{
    private const API_BASE = 'https://api.github.com';

    public function testConnection(string $repositoryUrl, ?string $token): array
    {
        $slug = $this->parseSlug($repositoryUrl);

        $response = $this->client($token)->get(self::API_BASE.'/repos/'.$slug);

        if ($response->status() === 404) {
            throw new \RuntimeException('Dépôt inaccessible : vérifiez l\'URL et les droits du jeton.');
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new \RuntimeException('Accès refusé par GitHub : jeton invalide ou limite d\'API atteinte.');
        }

        if ($response->failed()) {
            throw new \RuntimeException('Erreur GitHub (HTTP '.$response->status().').');
        }

        return [
            'name' => $response->json('full_name', $slug),
            'default_branch' => $response->json('default_branch', 'main'),
        ];
    }

    public function fetchCommits(string $repositoryUrl, ?string $token, string $branch, int $page, int $perPage): array
    {
        $slug = $this->parseSlug($repositoryUrl);

        $response = $this->client($token)
            ->get(self::API_BASE.'/repos/'.$slug.'/commits', [
                'sha' => $branch,
                'page' => $page,
                'per_page' => $perPage,
            ]);

        if ($response->status() === 404) {
            throw new \RuntimeException('Dépôt inaccessible : vérifiez l\'URL et les droits du jeton.');
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new \RuntimeException('Accès refusé par GitHub : jeton invalide ou limite d\'API atteinte.');
        }

        if ($response->failed()) {
            throw new \RuntimeException('Erreur GitHub (HTTP '.$response->status().').');
        }

        $commits = [];

        foreach ((array) $response->json() as $item) {
            $commits[] = [
                'sha' => $item['sha'],
                'message' => (string) $item['commit']['message'],
                'author_name' => $item['commit']['author']['name'] ?? null,
                'author_email' => $item['commit']['author']['email'] ?? null,
                'github_author' => $item['author']['login'] ?? null,
                'committed_at' => $item['commit']['author']['date'] ?? now()->toIso8601String(),
                'url' => $item['html_url'] ?? null,
                'branch' => $branch,
                'files' => [],
            ];
        }

        return $commits;
    }

    /**
     * Extrait « propriétaire/dépôt » d'une URL GitHub.
     */
    private function parseSlug(string $repositoryUrl): string
    {
        $host = parse_url($repositoryUrl, PHP_URL_HOST) ?? '';
        $path = trim((string) parse_url($repositoryUrl, PHP_URL_PATH), '/');

        if ($host !== '' && ! str_contains($host, 'github.com')) {
            throw new \RuntimeException('URL non supportée par ce connecteur (GitHub attendu).');
        }

        $parts = array_values(array_filter(explode('/', $path)));

        if (count($parts) < 2) {
            throw new \RuntimeException('URL de dépôt invalide : attendu https://github.com/propriétaire/dépôt.');
        }

        return $parts[0].'/'.$parts[1];
    }

    private function client(?string $token): PendingRequest
    {
        $client = Http::acceptJson()->timeout(15);

        if ($token !== null && $token !== '') {
            $client = $client->withToken($token);
        }

        return $client;
    }
}
