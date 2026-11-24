<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Authorization\EffectivePermissions;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Evaluation\Models\Competency;
use App\Domain\Evidence\Models\Evidence;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Internship\Models\Internship;
use App\Domain\Planning\Models\PlanEntry;
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
            'planEntries' => $this->planEntriesFor($internship),
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
            'plan_entry_ids' => ['nullable', 'array'],
            'plan_entry_ids.*' => ['uuid'],
        ]);

        $this->assertPlanEntriesBelong($internship, $validated['plan_entry_ids'] ?? []);

        $activity = Activity::query()->create(collect($validated)->except('plan_entry_ids')->all() + [
            'project_id' => $project->getKey(),
            'workspace_id' => $workspace->getKey(),
        ]);

        $this->syncPlanEntries($activity, $workspace, $validated['plan_entry_ids'] ?? []);

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

        // T-052 : portée « stage attribué » pour supervisor/tutor/viewer.
        $permissions = EffectivePermissions::resolveForInternship(auth()->user(), $workspace, $internship);
        abort_unless($permissions['view_activities'], 403);

        $user = auth()->user();

        // Preuves liées : les privées ne sont visibles que de leur
        // propriétaire (MT-07, pas de fuite de titre).
        $evidences = $activity->evidences()
            ->get()
            ->filter(fn (Evidence $evidence): bool => $evidence->visibility !== 'private'
                || $evidence->owner_id === $user->id);

        // Commentaires : les privés ne sont visibles que de leur auteur.
        $comments = $activity->comments()
            ->with('author')
            ->orderBy('created_at')
            ->get()
            ->filter(fn (Comment $comment): bool => $comment->visibility !== 'private'
                || $comment->author_id === $user->id);

        $reviews = $activity->reviews()
            ->with('reviewer')
            ->orderByDesc('created_at')
            ->get();

        // Compétences associées (T-060) et catalogue de la version du
        // espace de travail du stage.
        $competencies = $activity->activityCompetencies()
            ->with(['competency', 'assessments.assessor'])
            ->get()
            ->sortBy(fn ($association): string => $association->competency->code)
            ->values();

        $availableCompetencies = Competency::query()
            ->where('workspace_id', $internship->workspace_id)
            ->orderBy('code')
            ->get();

        // Candidats réviseurs : participants supervisor/tutor actifs.
        $reviewers = $internship->participants()
            ->with('user')
            ->whereIn('role', ['supervisor', 'tutor'])
            ->where('status', 'active')
            ->get();

        $canComment = $permissions['comment'];
        $canManage = $permissions['create_activity'];
        $canAssess = $permissions['assess'];

        return view('activities.show', [
            'workspace' => $workspace,
            'internship' => $internship,
            'project' => $project,
            'activity' => $activity,
            'planEntries' => $activity->planEntries()->orderBy('plan_date')->orderBy('slot')->orderBy('position')->get(),
            'tasks' => $activity->tasks()->orderBy('position')->get(),
            'evidences' => $evidences,
            'comments' => $comments,
            'reviews' => $reviews,
            'reviewers' => $reviewers,
            'competencies' => $competencies,
            'availableCompetencies' => $availableCompetencies,
            'canComment' => $canComment,
            'canManage' => $canManage,
            'canAssess' => $canAssess,
            'canReview' => $permissions['review'],
            'commentVisibilities' => $workspace->roleFor($user) === 'viewer'
                ? ['shared' => Comment::VISIBILITY_LABELS['shared']]
                : Comment::VISIBILITY_LABELS,
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
            'planEntries' => $this->planEntriesFor($internship),
            'linkedPlanEntryIds' => $activity->planEntries()->pluck('plan_entries.id')->all(),
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
            'plan_entry_ids' => ['nullable', 'array'],
            'plan_entry_ids.*' => ['uuid'],
        ]);

        $this->assertPlanEntriesBelong($internship, $validated['plan_entry_ids'] ?? []);

        $activity->fill(collect($validated)->except('plan_entry_ids')->all());
        $changed = array_keys($activity->getDirty());
        $activity->save();

        $this->syncPlanEntries($activity, $workspace, $validated['plan_entry_ids'] ?? []);

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

    /**
     * Entrées de planning du stage, triées par date et créneau.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PlanEntry>
     */
    private function planEntriesFor(Internship $internship)
    {
        return PlanEntry::query()
            ->where('internship_id', $internship->getKey())
            ->orderBy('plan_date')
            ->orderBy('slot')
            ->orderBy('position')
            ->get();
    }

    /**
     * Vérifie que chaque entrée de planning appartient au stage.
     *
     * @param  list<string>  $ids
     */
    private function assertPlanEntriesBelong(Internship $internship, array $ids): void
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return;
        }

        $found = PlanEntry::query()
            ->where('internship_id', $internship->getKey())
            ->whereIn('id', $ids)
            ->count();

        abort_unless($found === count($ids), 422);
    }

    /**
     * Synchronise les entrées de planning liées à l'activité.
     * Le workspace_id est injecté dans chaque pivot (clé composite).
     *
     * @param  list<string>  $planEntryIds
     */
    private function syncPlanEntries(Activity $activity, Workspace $workspace, array $planEntryIds): void
    {
        $activity->planEntries()->sync(
            collect(array_unique($planEntryIds))->mapWithKeys(
                static fn (string $id): array => [$id => ['workspace_id' => $workspace->getKey()]],
            )->all(),
        );
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
