<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Authorization\EffectivePermissions;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Notes\Models\Note;
use App\Domain\Notifications\NotificationService;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pense-bête (REQ-F-004) : notes personnelles ou partagées, échéances
 * et rappels. Les rappels sont déclenchés paresseusement au chargement
 * de la page (pas de cron) : une notification `note_reminder` est créée
 * une seule fois (`reminded_at`).
 */
class NoteController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request, Workspace $workspace): View
    {
        $user = $request->user();

        $this->dispatchReminders($workspace);

        $notes = $this->visibleNotes($workspace, $user)
            ->with(['user', 'internship', 'project'])
            ->when($request->query('scope') === 'mine', fn ($q) => $q->where('user_id', $user->id))
            ->when($request->query('scope') === 'shared', fn ($q) => $q->where('scope', 'workspace')->where('user_id', '!=', $user->id))
            ->when($request->query('status') === 'todo', fn ($q) => $q->where('status', 'todo'))
            ->when($request->query('status') === 'done', fn ($q) => $q->where('status', 'done'))
            ->when($request->query('overdue') === '1', fn ($q) => $q
                ->where('status', 'todo')
                ->whereNotNull('due_at')
                ->where('due_at', '<', now()))
            ->latest('created_at')
            ->get();

        $internships = $workspace->internships()->orderBy('title')->get(['id', 'title']);
        $projects = Project::query()
            ->whereIn('internship_id', $internships->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'name', 'internship_id']);

        return view('notes.index', [
            'workspace' => $workspace,
            'notes' => $notes,
            'internships' => $internships,
            'projects' => $projects,
            'filters' => $request->only(['scope', 'status', 'overdue']),
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $validated = $this->validateNote($request, $workspace);

        $note = Note::query()->create($validated + [
            'workspace_id' => $workspace->id,
            'user_id' => $request->user()->id,
            'status' => 'todo',
        ]);

        $this->audit->record('note.created', $workspace, $note, ['status' => $note->scope], $request->user());

        return redirect()->route('workspace.notes.index', $workspace);
    }

    public function update(Request $request, Workspace $workspace, string $note): RedirectResponse
    {
        $note = $this->findNote($workspace, $note);
        $this->authorizeWrite($note, $request->user(), $workspace);

        $validated = $this->validateNote($request, $workspace);

        $note->update($validated);

        return redirect()->route('workspace.notes.index', $workspace);
    }

    public function toggle(Request $request, Workspace $workspace, string $note): RedirectResponse
    {
        $note = $this->findNote($workspace, $note);
        $this->authorizeWrite($note, $request->user(), $workspace);

        $done = $note->status !== 'done';
        $note->update([
            'status' => $done ? 'done' : 'todo',
            'done_at' => $done ? now() : null,
        ]);

        return back();
    }

    public function destroy(Request $request, Workspace $workspace, string $note): RedirectResponse
    {
        $note = $this->findNote($workspace, $note);
        $this->authorizeWrite($note, $request->user(), $workspace);

        $this->audit->record('note.deleted', $workspace, $note, ['status' => $note->scope], $request->user());
        $note->delete();

        return redirect()->route('workspace.notes.index', $workspace);
    }

    // --- Helpers ---------------------------------------------------------

    /**
     * Notes visibles : personnelles → auteur ; partagées non liées → tous
     * les membres ; liées à un stage/projet → membres ayant `view_internship`.
     *
     * @return Builder<Note>
     */
    private function visibleNotes(Workspace $workspace, User $user)
    {
        $linkedIds = Note::query()
            ->where('workspace_id', $workspace->id)
            ->where('scope', 'workspace')
            ->where(fn ($q) => $q->whereNotNull('internship_id')->orWhereNotNull('project_id'))
            ->get(['id', 'internship_id', 'project_id', 'user_id']);

        $visibleLinked = $linkedIds
            ->filter(fn (Note $n) => $this->canViewLinked($workspace, $user, $n))
            ->pluck('id');

        return Note::query()
            ->where('workspace_id', $workspace->id)
            ->where(function ($q) use ($user, $visibleLinked): void {
                $q->where(fn ($p) => $p->where('scope', 'personal')->where('user_id', $user->id))
                    ->orWhere(fn ($p) => $p->where('scope', 'workspace')->whereNull('internship_id')->whereNull('project_id'))
                    ->orWhereIn('id', $visibleLinked);
            });
    }

    private function canViewLinked(Workspace $workspace, User $user, Note $note): bool
    {
        $internship = $note->internship_id !== null
            ? $workspace->internships()->find($note->internship_id)
            : $note->project?->internship;

        if ($internship === null) {
            return $note->user_id === $user->id;
        }

        return EffectivePermissions::resolveForInternship($user, $workspace, $internship)['view_internship'];
    }

    private function findNote(Workspace $workspace, string $id): Note
    {
        return Note::query()->where('workspace_id', $workspace->id)->findOrFail($id);
    }

    private function authorizeWrite(Note $note, User $user, Workspace $workspace): void
    {
        $isManager = in_array($workspace->roleFor($user), ['owner', 'admin'], true);
        abort_unless($note->user_id === $user->id || $isManager, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateNote(Request $request, Workspace $workspace): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:5000'],
            'scope' => ['required', Rule::in(Note::SCOPES)],
            'due_at' => ['nullable', 'date'],
            'remind_at' => ['nullable', 'date'],
            'internship_id' => ['nullable', 'uuid'],
            'project_id' => ['nullable', 'uuid'],
        ]);

        // Une note personnelle ne peut pas être partagée ni liée à autrui.
        if (($validated['scope'] ?? 'personal') === 'personal') {
            $validated['internship_id'] = null;
            $validated['project_id'] = null;
        }

        // Les rattachements doivent appartenir au workspace.
        if (! empty($validated['internship_id'])) {
            abort_unless(
                $workspace->internships()->whereKey($validated['internship_id'])->exists(),
                422,
            );
        }
        if (! empty($validated['project_id'])) {
            abort_unless(
                Project::query()
                    ->where('workspace_id', $workspace->id)
                    ->whereKey($validated['project_id'])
                    ->exists(),
                422,
            );
        }

        return $validated;
    }

    /**
     * Crée une notification `note_reminder` pour chaque note dont
     * `remind_at` est dépassé et pas encore rappelée.
     */
    private function dispatchReminders(Workspace $workspace): void
    {
        $due = Note::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'todo')
            ->whereNotNull('remind_at')
            ->where('remind_at', '<=', now())
            ->whereNull('reminded_at')
            ->with('user')
            ->get();

        foreach ($due as $note) {
            DB::transaction(function () use ($note, $workspace): void {
                $fresh = Note::query()->lockForUpdate()->find($note->id);
                if ($fresh === null || $fresh->reminded_at !== null) {
                    return;
                }

                $this->notifications->send(
                    $workspace,
                    $fresh->user,
                    'note_reminder',
                    'Rappel : '.$fresh->title,
                    $fresh->body,
                    [
                        'url' => route('workspace.notes.index', $workspace),
                        'dedupe_key' => 'note_reminder:'.$fresh->id,
                    ],
                );

                $fresh->forceFill(['reminded_at' => now()])->save();
            });
        }
    }
}
