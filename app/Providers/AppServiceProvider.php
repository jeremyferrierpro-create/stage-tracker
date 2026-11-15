<?php

namespace App\Providers;

use App\Domain\Identity\WorkspaceContext;
use App\Integrations\Storage\EvidenceStorageInterface;
use App\Integrations\Storage\LocalEvidenceStorage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WorkspaceContext::class);
        $this->app->bind(
            EvidenceStorageInterface::class,
            LocalEvidenceStorage::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
