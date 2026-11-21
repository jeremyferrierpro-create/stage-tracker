<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Authorization\EffectivePermissions;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Internship\Models\Internship;
use App\Domain\Internship\Models\InternshipParticipant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InternshipController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Workspace $workspace): View
    {
        $user = auth()->user();
        $this->authorize('view', $workspace);

        $query = $workspace->internships()
            ->with('trainee')
            ->orderByDesc('start_date');

        // Portée « stage attribué » (T-052) : supervisor/tutor/viewer ne
        // voient que les stages où ils sont rattachés actifs.
        if (in_array($workspace->roleFor($user), ['supervisor', 'tutor', 'viewer'], true)) {
            $query->whereHas('participants', function ($q) use ($user): void {
                $q->where('user_id', $user->id)->where('status', 'active');
            });
        }

        return view('internships.index', [
            'workspace' => $workspace,
            'internships' => $query->get(),
        ]);
    }

    public function create(Workspace $workspace): View
    {
        $this->authorize('manageInternships', $workspace);

        return view('internships.create', [
            'workspace' => $workspace,
            'users' => $workspace->users()->wherePivot('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('manageInternships', $workspace);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'trainee_id' => ['required', 'exists:users,id'],
            'company_name' => ['required', 'string', 'max:200'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'objectives' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', 'in:draft,active,completed,archived'],
        ]);

        $internship = $workspace->internships()->create($validated);

        $this->audit->record(
            'internship.created',
            $workspace,
            $internship,
            [
                'subject' => $internship->title,
                'status' => $internship->status,
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.internships.show', [$workspace, $internship])
            ->with('status', 'Stage créé.');
    }

    public function show(Workspace $workspace, string $internship): View
    {
        $this->authorize('view', $workspace);

        $internship = $this->resolve($workspace, $internship);

        abort_unless(
            EffectivePermissions::resolveForInternship(auth()->user(), $workspace, $internship)['view_internship'],
            403,
        );

        return view('internships.show', [
            'workspace' => $workspace,
            'internship' => $internship->load(['trainee', 'participants.user', 'projects']),
        ]);
    }

    public function edit(Workspace $workspace, string $internship): View
    {
        $internship = $this->resolve($workspace, $internship);

        $this->authorizeManage($workspace, $internship);

        return view('internships.edit', [
            'workspace' => $workspace,
            'internship' => $internship,
        ]);
    }

    public function update(Request $request, Workspace $workspace, string $internship): RedirectResponse
    {
        $internship = $this->resolve($workspace, $internship);

        $this->authorizeManage($workspace, $internship);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'company_name' => ['required', 'string', 'max:200'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'objectives' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', 'in:draft,active,completed,archived'],
        ]);

        $internship->fill($validated);
        $changed = array_keys($internship->getDirty());
        $internship->save();

        $this->audit->record(
            'internship.updated',
            $workspace,
            $internship,
            [
                'subject' => $internship->title,
                'status' => $internship->status,
                'changed' => implode(',', $changed),
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.internships.show', [$workspace, $internship])
            ->with('status', 'Stage mis à jour.');
    }

    public function storeParticipant(Request $request, Workspace $workspace, string $internship): RedirectResponse
    {
        $internship = $this->resolve($workspace, $internship);

        $this->authorizeManage($workspace, $internship);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', 'string', Rule::in(InternshipParticipant::ROLES)],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        if ($workspace->roleFor($user) === null) {
            return redirect()
                ->route('workspace.internships.show', [$workspace, $internship])
                ->with('status', 'L\'utilisateur doit être membre actif de l\'espace.');
        }

        $participant = InternshipParticipant::query()->create([
            'internship_id' => $internship->getKey(),
            'workspace_id' => $workspace->getKey(),
            'user_id' => $validated['user_id'],
            'role' => $validated['role'],
            'permissions' => [],
            'status' => 'active',
        ]);

        $this->audit->record(
            'internship.participant.added',
            $workspace,
            $participant,
            [
                'subject' => $internship->title,
                'role' => $participant->role,
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.internships.show', [$workspace, $internship])
            ->with('status', 'Participant ajouté.');
    }

    public function destroyParticipant(Workspace $workspace, string $internship, string $participant): RedirectResponse
    {
        $internship = $this->resolve($workspace, $internship);

        $this->authorizeManage($workspace, $internship);

        $participantModel = $internship->participants()->findOrFail($participant);

        // T-053 : révocation sans cascade destructive — le participant
        // perd l'accès immédiatement mais son historique est conservé.
        if ($participantModel->role === 'trainee') {
            return redirect()
                ->back()
                ->withErrors(['participant' => 'Le stagiaire du stage ne peut pas être révoqué (transfert non pris en charge).']);
        }

        $participantModel->forceFill(['status' => 'removed'])->save();

        $this->audit->record(
            'internship.participant.revoked',
            $workspace,
            $participantModel,
            [
                'role' => $participantModel->role,
                'status' => 'removed',
            ],
            auth()->user(),
        );

        return redirect()
            ->route('workspace.internships.show', [$workspace, $internship])
            ->with('status', 'Participant révoqué (historique conservé).');
    }

    public function reactivateParticipant(Workspace $workspace, string $internship, string $participant): RedirectResponse
    {
        $internship = $this->resolve($workspace, $internship);

        $this->authorizeManage($workspace, $internship);

        $participantModel = $internship->participants()->findOrFail($participant);

        $participantModel->forceFill(['status' => 'active'])->save();

        $this->audit->record(
            'internship.participant.reactivated',
            $workspace,
            $participantModel,
            [
                'role' => $participantModel->role,
                'status' => 'active',
            ],
            auth()->user(),
        );

        return redirect()
            ->route('workspace.internships.show', [$workspace, $internship])
            ->with('status', 'Participant réactivé.');
    }

    private function resolve(Workspace $workspace, string $internship): Internship
    {
        return $workspace->internships()->findOrFail($internship);
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
