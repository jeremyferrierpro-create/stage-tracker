<?php

namespace App\Http\Controllers;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\Workspace;
use Illuminate\View\View;

/**
 * Consultation du journal d'audit d'un workspace (T-009).
 * Accès réservé aux propriétaires et administrateurs (SECURITY §3.6).
 */
class AuditLogController extends Controller
{
    public function index(Workspace $workspace): View
    {
        $this->authorize('viewAudit', $workspace);

        return view('workspace.audit', [
            'workspace' => $workspace,
            'logs' => AuditLog::query()
                ->where('workspace_id', $workspace->id)
                ->with('actor')
                ->orderByDesc('id')
                ->paginate(50),
        ]);
    }
}
