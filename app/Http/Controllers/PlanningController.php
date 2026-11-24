<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Authorization\EffectivePermissions;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Internship\Models\Internship;
use App\Domain\Planning\Models\PlanEntry;
use App\Domain\Planning\PlanningComparisonService;
use App\Domain\Project\Models\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PlanningController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Workspace $workspace, string $internship): View
    {
        $this->authorize('view', $workspace);

        $internship = $workspace->internships()->findOrFail($internship);
        $permissions = $this->permissions($workspace, $internship);

        abort_unless($permissions['view_journal'], 403);

        $days = app(PlanningComparisonService::class)->daysFor($internship);

        // Charge les activités liées à chaque entrée pour affichage.
        $entryIds = collect($days)->flatMap(
            static fn (array $day): array => collect($day['entries'])->flatten(1)->map(
                static fn (PlanEntry $entry): string => $entry->getKey(),
            )->all(),
        );

        $activitiesByEntry = PlanEntry::query()
            ->whereIn('id', $entryIds)
            ->with('activities.project:id,name')
            ->get()
            ->mapWithKeys(static fn (PlanEntry $entry) => [
                $entry->getKey() => $entry->activities,
            ]);

        return view('planning.show', [
            'workspace' => $workspace,
            'internship' => $internship,
            'days' => $days,
            'projects' => $internship->projects()->orderBy('name')->get(),
            'canManage' => $permissions['manage_planning'],
            'activitiesByEntry' => $activitiesByEntry,
        ]);
    }

    public function store(Request $request, Workspace $workspace, string $internship): RedirectResponse
    {
        $internship = $workspace->internships()->findOrFail($internship);
        $this->authorizeManage($workspace, $internship);

        $validated = $request->validate([
            'plan_date' => ['required', 'date'],
            'slot' => ['required', 'string', 'in:'.implode(',', PlanEntry::SLOTS)],
            'title' => ['required', 'string', 'max:200'],
            'planned_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'project_id' => ['nullable', 'uuid'],
        ]);

        $this->assertProjectBelongs($internship, $validated['project_id'] ?? null);

        $position = PlanEntry::query()
            ->where('internship_id', $internship->getKey())
            ->where('plan_date', $validated['plan_date'])
            ->where('slot', $validated['slot'])
            ->max('position') ?? -1;

        $entry = PlanEntry::query()->create(array_merge($validated, [
            'internship_id' => $internship->getKey(),
            'workspace_id' => $workspace->getKey(),
            'position' => $position + 1,
        ]));

        $this->audit->record(
            'plan_entry.created',
            $workspace,
            $entry,
            ['subject' => $entry->title, 'plan_date' => $entry->plan_date->toDateString()],
            $request->user(),
        );

        return back()->with('status', 'Entrée de planning ajoutée.');
    }

    public function update(Request $request, Workspace $workspace, string $internship, string $entry): RedirectResponse
    {
        $internship = $workspace->internships()->findOrFail($internship);
        $this->authorizeManage($workspace, $internship);

        $entry = PlanEntry::query()
            ->where('internship_id', $internship->getKey())
            ->findOrFail($entry);

        $validated = $request->validate([
            'plan_date' => ['required', 'date'],
            'slot' => ['required', 'string', 'in:'.implode(',', PlanEntry::SLOTS)],
            'title' => ['required', 'string', 'max:200'],
            'planned_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'project_id' => ['nullable', 'uuid'],
        ]);

        $this->assertProjectBelongs($internship, $validated['project_id'] ?? null);

        $entry->fill($validated);
        $entry->save();

        $this->audit->record(
            'plan_entry.updated',
            $workspace,
            $entry,
            ['subject' => $entry->title, 'plan_date' => $entry->plan_date->toDateString()],
            $request->user(),
        );

        return back()->with('status', 'Entrée de planning mise à jour.');
    }

    public function destroy(Request $request, Workspace $workspace, string $internship, string $entry): RedirectResponse
    {
        $internship = $workspace->internships()->findOrFail($internship);
        $this->authorizeManage($workspace, $internship);

        $entry = PlanEntry::query()
            ->where('internship_id', $internship->getKey())
            ->findOrFail($entry);

        $this->audit->record(
            'plan_entry.deleted',
            $workspace,
            $entry,
            ['subject' => $entry->title, 'plan_date' => $entry->plan_date->toDateString()],
            $request->user(),
        );

        $entry->delete();

        return back()->with('status', 'Entrée de planning supprimée.');
    }

    /**
     * Crée une activité réelle à partir d'une entrée planifiée et la lie.
     * Les preuves se déposent ensuite sur la page de l'activité.
     */
    public function createActivity(Request $request, Workspace $workspace, string $internship, string $entry): RedirectResponse
    {
        $internship = $workspace->internships()->findOrFail($internship);
        $this->authorizeManage($workspace, $internship);

        $entry = PlanEntry::query()
            ->where('internship_id', $internship->getKey())
            ->findOrFail($entry);

        $validated = $request->validate([
            'project_id' => ['required', 'uuid'],
            'type' => ['required', 'string', 'in:'.implode(',', Activity::TYPES)],
            'status' => ['required', 'string', 'in:'.implode(',', Activity::STATUSES)],
            'priority' => ['required', 'string', 'in:'.implode(',', Activity::PRIORITIES)],
        ]);

        $project = $internship->projects()->findOrFail($validated['project_id']);

        $activity = DB::transaction(function () use ($entry, $project, $workspace, $validated): Activity {
            $activity = Activity::query()->create([
                'project_id' => $project->getKey(),
                'workspace_id' => $workspace->getKey(),
                'title' => $entry->title,
                'type' => $validated['type'],
                'status' => $validated['status'],
                'priority' => $validated['priority'],
            ]);

            $entry->activities()->attach($activity->getKey(), [
                'workspace_id' => $workspace->getKey(),
            ]);

            return $activity;
        });

        $this->audit->record(
            'plan_entry.activity_created',
            $workspace,
            $activity,
            [
                'subject' => $activity->title,
                'plan_entry' => $entry->plan_date->toDateString().' '.$entry->slot,
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.internships.projects.activities.show', [
                $workspace, $internship, $project, $activity,
            ])
            ->with('status', 'Activité créée depuis le planning et liée à l\'entrée.');
    }

    /**
     * @return array<string, bool>
     */
    private function permissions(Workspace $workspace, Internship $internship): array
    {
        return EffectivePermissions::resolveForInternship(auth()->user(), $workspace, $internship);
    }

    private function authorizeManage(Workspace $workspace, Internship $internship): void
    {
        $this->authorize('view', $workspace);

        abort_unless(
            $this->permissions($workspace, $internship)['manage_planning'],
            403,
        );
    }

    private function assertProjectBelongs(Internship $internship, ?string $projectId): void
    {
        if ($projectId === null) {
            return;
        }

        abort_unless(
            $internship->projects()->whereKey($projectId)->exists(),
            422,
        );
    }
}
