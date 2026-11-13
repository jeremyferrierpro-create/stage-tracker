<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Internship\Models\Internship;
use App\Domain\Project\Models\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Workspace $workspace, string $internship, string $project): View
    {
        $this->authorize('view', $workspace);

        $internship = $workspace->internships()->findOrFail($internship);
        $project = $internship->projects()->findOrFail($project);

        return view('activities.index', [
            'workspace' => $workspace,
            'internship' => $internship,
            'project' => $project,
            'activities' => $project->activities()->orderBy('created_at')->get(),
        ]);
    }

    public function create(Workspace $workspace, string $internship, string $project): View
    {
        $internship = $workspace->internships()->findOrFail($internship);
        $project = $internship->projects()->findOrFail($project);

        $this->authorizeManage($workspace, $internship);

        return view('activities.create', [
            'workspace' => $workspace,
            'internship' => $internship,
            'project' => $project,
        ]);
    }

    public function store(Request $request, Workspace $workspace, string $internship, string $project): RedirectResponse
    {
        $internship = $workspace->internships()->findOrFail($internship);
        $project = $internship->projects()->findOrFail($project);

        $this->authorizeManage($workspace, $internship);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', 'string', 'in:feature,fix,study,design,research,maintenance,improvement'],
            'status' => ['required', 'string', 'in:todo,in_progress,to_review,done,blocked'],
            'priority' => ['required', 'string', 'in:low,medium,high,critical'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'objectives' => ['nullable', 'string', 'max:5000'],
            'results' => ['nullable', 'string', 'max:5000'],
            'difficulties' => ['nullable', 'string', 'max:5000'],
            'solutions' => ['nullable', 'string', 'max:5000'],
        ]);

        $activity = Activity::query()->create(array_merge($validated, [
            'project_id' => $project->getKey(),
            'workspace_id' => $workspace->getKey(),
        ]));

        $this->audit->record(
            'activity.created',
            $workspace,
            $activity,
            [
                'subject' => $activity->title,
                'status' => $activity->status,
                'type' => $activity->type,
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.internships.projects.activities.show', [$workspace, $internship, $project, $activity])
            ->with('status', 'Activité créée.');
    }

    public function show(Workspace $workspace, string $internship, string $project, string $activity): View
    {
        $this->authorize('view', $workspace);

        $internship = $workspace->internships()->findOrFail($internship);
        $project = $internship->projects()->findOrFail($project);
        $activity = $project->activities()->findOrFail($activity);

        return view('activities.show', [
            'workspace' => $workspace,
            'internship' => $internship,
            'project' => $project,
            'activity' => $activity,
        ]);
    }

    public function edit(Workspace $workspace, string $internship, string $project, string $activity): View
    {
        $internship = $workspace->internships()->findOrFail($internship);
        $project = $internship->projects()->findOrFail($project);
        $activity = $project->activities()->findOrFail($activity);

        $this->authorizeManage($workspace, $internship);

        return view('activities.edit', [
            'workspace' => $workspace,
            'internship' => $internship,
            'project' => $project,
            'activity' => $activity,
        ]);
    }

    public function update(Request $request, Workspace $workspace, string $internship, string $project, string $activity): RedirectResponse
    {
        $internship = $workspace->internships()->findOrFail($internship);
        $project = $internship->projects()->findOrFail($project);
        $activity = $project->activities()->findOrFail($activity);

        $this->authorizeManage($workspace, $internship);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', 'string', 'in:feature,fix,study,design,research,maintenance,improvement'],
            'status' => ['required', 'string', 'in:todo,in_progress,to_review,done,blocked'],
            'priority' => ['required', 'string', 'in:low,medium,high,critical'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'objectives' => ['nullable', 'string', 'max:5000'],
            'results' => ['nullable', 'string', 'max:5000'],
            'difficulties' => ['nullable', 'string', 'max:5000'],
            'solutions' => ['nullable', 'string', 'max:5000'],
        ]);

        $activity->fill($validated);
        $changed = array_keys($activity->getDirty());
        $activity->save();

        $this->audit->record(
            'activity.updated',
            $workspace,
            $activity,
            [
                'subject' => $activity->title,
                'status' => $activity->status,
                'type' => $activity->type,
                'changed' => implode(',', $changed),
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.internships.projects.activities.show', [$workspace, $internship, $project, $activity])
            ->with('status', 'Activité mise à jour.');
    }

    public function archive(Workspace $workspace, string $internship, string $project, string $activity): RedirectResponse
    {
        $internship = $workspace->internships()->findOrFail($internship);
        $project = $internship->projects()->findOrFail($project);
        $activity = $project->activities()->findOrFail($activity);

        $this->authorizeManage($workspace, $internship);

        if ($activity->archived_at === null) {
            $activity->forceFill(['archived_at' => now()])->save();

            $this->audit->record(
                'activity.archived',
                $workspace,
                $activity,
                [
                    'subject' => $activity->title,
                    'status' => $activity->status,
                ],
                auth()->user(),
            );
        }

        return redirect()
            ->route('workspace.internships.projects.activities.index', [$workspace, $internship, $project])
            ->with('status', 'Activité archivée.');
    }

    private function authorizeManage(Workspace $workspace, Internship $internship): void
    {
        $user = auth()->user();
        $isManager = in_array($workspace->roleFor($user), ['owner', 'admin'], true);
        $isTrainee = $internship->trainee_id === $user?->id;

        if (! $isManager && ! $isTrainee) {
            abort(403);
        }
    }
}
