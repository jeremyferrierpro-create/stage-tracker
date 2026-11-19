<?php

namespace Tests\Feature\Git;

use App\Integrations\GitHub\GitHubProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GitHubProviderTest extends TestCase
{
    private GitHubProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new GitHubProvider;
    }

    public function test_fetch_commit_files_returns_mapped_files_on_success(): void
    {
        Http::fake([
            'https://api.github.com/repos/acme/project/commits/abc123456789' => Http::response([
                'sha' => 'abc123456789',
                'files' => [
                    [
                        'filename' => 'app/Services/Sync.php',
                        'status' => 'added',
                        'additions' => 45,
                        'deletions' => 0,
                    ],
                    [
                        'filename' => 'README.md',
                        'status' => 'modified',
                        'additions' => 5,
                        'deletions' => 2,
                    ],
                    [
                        'filename' => 'old_script.py',
                        'status' => 'removed',
                        'additions' => 0,
                        'deletions' => 12,
                    ],
                    [
                        'filename' => 'renamed_file.php',
                        'status' => 'renamed',
                        'additions' => 1,
                        'deletions' => 1,
                    ],
                ],
            ], 200),
        ]);

        $files = $this->provider->fetchCommitFiles(
            'https://github.com/acme/project',
            'ghp_faketoken',
            'abc123456789'
        );

        $this->assertCount(4, $files);
        $this->assertSame([
            'path' => 'app/Services/Sync.php',
            'change_type' => 'added',
            'additions' => 45,
            'deletions' => 0,
        ], $files[0]);

        $this->assertSame([
            'path' => 'README.md',
            'change_type' => 'modified',
            'additions' => 5,
            'deletions' => 2,
        ], $files[1]);

        $this->assertSame([
            'path' => 'old_script.py',
            'change_type' => 'deleted',
            'additions' => 0,
            'deletions' => 12,
        ], $files[2]);

        $this->assertSame([
            'path' => 'renamed_file.php',
            'change_type' => 'renamed',
            'additions' => 1,
            'deletions' => 1,
        ], $files[3]);
    }

    public function test_fetch_commit_files_throws_on_not_found(): void
    {
        Http::fake([
            'https://api.github.com/repos/acme/project/commits/missing' => Http::response([], 404),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Commit introuvable ou dépôt inaccessible.');

        $this->provider->fetchCommitFiles(
            'https://github.com/acme/project',
            null,
            'missing'
        );
    }

    public function test_fetch_commit_files_throws_on_unauthorized_or_forbidden(): void
    {
        Http::fake([
            'https://api.github.com/repos/acme/project/commits/sha1' => Http::response([], 403),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Accès refusé par GitHub : jeton invalide ou limite d'API atteinte.");

        $this->provider->fetchCommitFiles(
            'https://github.com/acme/project',
            'invalid_token',
            'sha1'
        );
    }

    public function test_fetch_commits_includes_since_query_parameter_when_provided(): void
    {
        Http::fake([
            'https://api.github.com/repos/acme/project/commits*' => Http::response([
                [
                    'sha' => 'sha1000',
                    'commit' => [
                        'message' => 'commit test',
                        'author' => [
                            'name' => 'Alice',
                            'email' => 'alice@example.com',
                            'date' => '2026-11-19T10:00:00Z',
                        ],
                    ],
                    'html_url' => 'https://github.com/acme/project/commit/sha1000',
                ],
            ], 200),
        ]);

        $sinceDate = '2026-11-18T10:00:00Z';
        $commits = $this->provider->fetchCommits(
            'https://github.com/acme/project',
            'dummy_token',
            'main',
            1,
            30,
            $sinceDate
        );

        $this->assertCount(1, $commits);
        $this->assertSame('sha1000', $commits[0]['sha']);

        Http::assertSent(function ($request) use ($sinceDate) {
            return str_contains($request->url(), 'since='.urlencode($sinceDate));
        });
    }
}
