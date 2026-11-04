<?php

namespace App\Providers;

use App\Domain\Identity\Models\Workspace;
use App\Policies\WorkspacePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Workspace::class, WorkspacePolicy::class);
    }
}
