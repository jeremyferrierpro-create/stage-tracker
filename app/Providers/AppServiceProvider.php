<?php

namespace App\Providers;

use App\Domain\Identity\WorkspaceContext;
use App\Integrations\Git\GitProviderInterface;
use App\Integrations\GitHub\GitHubProvider;
use App\Integrations\Storage\EvidenceStorageInterface;
use App\Integrations\Storage\LocalEvidenceStorage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WorkspaceContext::class);
        $this->app->bind(
            EvidenceStorageInterface::class,
            LocalEvidenceStorage::class,
        );
        $this->app->bind(
            GitProviderInterface::class,
            GitHubProvider::class,
        );
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}