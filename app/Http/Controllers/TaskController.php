<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Internship\Models\Internship;
use App\Domain\Project\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function store(Request $request, Workspace $workspace, string $internship, string $project, string $activity): RedirectResponse
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);
        $projectModel = $internshipModel->projects()->findOrFail($project);
        $activityModel = $projectModel->activities()->findOrFail($activity);

        $this->authorizeManage($workspace, $internshipModel);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', 'string', 'in:low,medium,high,critical'],
            'status' => ['required', 'string', 'in:todo,in_progress,to_review,done,blocked'],
            'due_date' => ['nullable', 'date'],
            'acceptance_criteria' => ['nullable', 'string', 'max:5000'],
        ]);

        $maxPosition = (int) $activityModel->tasks()->max('position');
        $task = Task::query()->create(array_merge($validated, [
            'activity_id' => $activityModel->getKey(),
            'workspace_id' => $workspace->getKey(),
            'position' => $maxPosition + 1,
        ]));

        $this->audit->record(
            'task.created',
            $workspace,
            $task,
            [
                'subject' => $task->title,
                'status' => $task->status,
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.internships.projects.activities.show', [$workspace, $internship, $project, $activity])
            ->with('status', 'Tâche créée.');
    }

    public function update(Request $request, Workspace $workspace, string $internship, string $project, string $activity, string $task): RedirectResponse
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);
        $projectModel = $internshipModel->projects()->findOrFail($project);
        $activityModel = $projectModel->activities()->findOrFail($activity);
        $taskModel = $activityModel->tasks()->findOrFail($task);

        $this->authorizeManage($workspace, $internshipModel);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['sometimes', 'required', 'string', 'in:low,medium,high,critical'],
            'status' => ['sometimes', 'required', 'string', 'in:todo,in_progress,to_review,done,blocked'],
            'due_date' => ['nullable', 'date'],
            'acceptance_criteria' => ['nullable', 'string', 'max:5000'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $taskModel->fill($validated);
        $changed = array_keys($taskModel->getDirty());
        $taskModel->save();

        $this->audit->record(
            'task.updated',
            $workspace,
            $taskModel,
            [
                'subject' => $taskModel->title,
                'status' => $taskModel->status,
                'changed' => implode(',', $changed),
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.internships.projects.activities.show', [$workspace, $internship, $project, $activity])
            ->with('status', 'Tâche mise à jour.');
    }

    public function destroy(Workspace $workspace, string $internship, string $project, string $activity, string $task): RedirectResponse
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);
        $projectModel = $internshipModel->projects()->findOrFail($project);
        $activityModel = $projectModel->activities()->findOrFail($activity);
        $taskModel = $activityModel->tasks()->findOrFail($task);

        $this->authorizeManage($workspace, $internshipModel);

        $taskModel->delete();

        return redirect()
            ->route('workspace.internships.projects.activities.show', [$workspace, $internship, $project, $activity])
            ->with('status', 'Tâche supprimée.');
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
