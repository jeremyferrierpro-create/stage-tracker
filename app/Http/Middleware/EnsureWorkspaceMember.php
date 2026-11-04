<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\WorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Couche 1 de la défense en profondeur (ADR-002) : résolution du workspace
 * courant et refus (403) de tout utilisateur non membre actif.
 */
class EnsureWorkspaceMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->route('workspace');

        if (! $workspace instanceof Workspace) {
            abort(404);
        }

        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $membership = $workspace->members()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($membership === null) {
            abort(403);
        }

        $context = app(WorkspaceContext::class);
        $context->set($workspace);
        $request->attributes->set('workspace_membership', $membership);

        try {
            return $next($request);
        } finally {
            // Évite toute fuite de contexte entre requêtes (Octane, tests).
            $context->clear();
        }
    }
}
