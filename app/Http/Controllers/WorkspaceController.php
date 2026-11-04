<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function index(Request $request): View
    {
        $workspaces = $request->user()
            ->workspaces()
            ->wherePivot('status', 'active')
            ->orderBy('name')
            ->get();

        return view('workspaces.index', [
            'workspaces' => $workspaces,
        ]);
    }

    public function dashboard(Request $request, Workspace $workspace): View
    {
        return view('workspace.dashboard', [
            'workspace' => $workspace,
            'membership' => $request->attributes->get('workspace_membership'),
        ]);
    }
}
