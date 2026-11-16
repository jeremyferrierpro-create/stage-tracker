<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Authorization\EffectivePermissions;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Internship\Models\Internship;
use App\Domain\Project\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Workspace $workspace, string $internship): View
    {
        $this->authorize('view', $workspace);

        $internship = $workspace->internships()->findOrFail($internship);

        return view('projects.index', [
            'workspace' => $workspace,
            'internship' => $internship,
            'projects' => $internship->projects()->withCount('activities')->orderBy('name')->get(),
        ]);
    }

    public function create(Workspace $workspace, string $internship): View
    {
        $internship = $workspace->internships()->findOrFail($internship);

        $this->authorizeManage($workspace, $internship);

        return view('projects.create', [
            'workspace' => $workspace,
            'internship' => $internship,
        ]);
    }

    public function store(Request $request, Workspace $workspace, string $internship): RedirectResponse
    {
        $internship = $workspace->internships()->findOrFail($internship);

        $this->authorizeManage($workspace, $internship);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', 'in:active,completed,archived'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $project = Project::query()->create(array_merge($validated, [
            'internship_id' => $internship->getKey(),
            'workspace_id' => $workspace->getKey(),
        ]));

        $this->audit->record(
            'project.created',
            $workspace,
            $project,
            [
                'subject' => $project->name,
                'status' => $project->status,
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.internships.projects.show', [$workspace, $internship, $project])
            ->with('status', 'Projet créé.');
    }

    public function show(Workspace $workspace, string $internship, string $project): View
    {
        $this->authorize('view', $workspace);

        $internship = $workspace->internships()->findOrFail($internship);

        // T-052 : portée « stage attribué » pour supervisor/tutor/viewer.
        abort_unless(
            EffectivePermissions::resolveForInternship(auth()->user(), $workspace, $internship)['view_internship'],
            403,
        );
        $project = $internship->projects()->findOrFail($project);

        return view('projects.show', [
            'workspace' => $workspace,
            'internship' => $internship,
            'project' => $project->load(['activities', 'repositories']),
        ]);
    }

    public function edit(Workspace $workspace, string $internship, string $project): View
    {
        $internship = $workspace->internships()->findOrFail($internship);
        $project = $internship->projects()->findOrFail($project);

        $this->authorizeManage($workspace, $internship);

        return view('projects.edit', [
            'workspace' => $workspace,
            'internship' => $internship,
            'project' => $project,
        ]);
    }

    public function update(Request $request, Workspace $workspace, string $internship, string $project): RedirectResponse
    {
        $internship = $workspace->internships()->findOrFail($internship);
        $project = $internship->projects()->findOrFail($project);

        $this->authorizeManage($workspace, $internship);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', 'in:active,completed,archived'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $project->fill($validated);
        $changed = array_keys($project->getDirty());
        $project->save();

        $this->audit->record(
            'project.updated',
            $workspace,
            $project,
            [
                'subject' => $project->name,
                'status' => $project->status,
                'changed' => implode(',', $changed),
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.internships.projects.show', [$workspace, $internship, $project])
            ->with('status', 'Projet mis à jour.');
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
