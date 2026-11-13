<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Models\Workspace;
use App\Domain\Internship\Models\Internship;
use App\Domain\Journal\Models\DailyLog;
use App\Domain\Project\Models\Activity;
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
        $this->authorize('view', $workspace);

        $internships = $workspace->internships()
            ->with(['trainee', 'hostOrganization'])
            ->orderByDesc('start_date')
            ->get();

        $activities = Activity::query()
            ->where('workspace_id', $workspace->getKey())
            ->with('project.internship')
            ->get();

        $activityCounts = [];
        foreach (Activity::STATUSES as $status) {
            $activityCounts[$status] = $activities->where('status', $status)->count();
        }

        $recentLogs = DailyLog::query()
            ->where('workspace_id', $workspace->getKey())
            ->with(['author', 'internship'])
            ->orderByDesc('work_date')
            ->limit(5)
            ->get();

        $referencedVersions = Internship::query()
            ->where('workspace_id', $workspace->getKey())
            ->with('frameworkVersion.framework')
            ->get()
            ->pluck('frameworkVersion')
            ->filter()
            ->unique('id')
            ->values();

        return view('workspace.dashboard', [
            'workspace' => $workspace,
            'membership' => $request->attributes->get('workspace_membership'),
            'internships' => $internships,
            'activityCounts' => $activityCounts,
            'totalActivities' => $activities->count(),
            'recentLogs' => $recentLogs,
            'referencedVersions' => $referencedVersions,
        ]);
    }
}
