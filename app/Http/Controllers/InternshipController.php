<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Framework\Models\CompetencyFramework;
use App\Domain\Framework\Models\FrameworkVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Internship\Models\Internship;
use App\Domain\Internship\Models\InternshipParticipant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InternshipController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Workspace $workspace): View
    {
        $this->authorize('view', $workspace);

        return view('internships.index', [
            'workspace' => $workspace,
            'internships' => $workspace->internships()
                ->with(['trainee', 'hostOrganization', 'frameworkVersion'])
                ->orderByDesc('start_date')
                ->get(),
        ]);
    }

    public function create(Workspace $workspace): View
    {
        $this->authorize('manageInternships', $workspace);

        return view('internships.create', [
            'workspace' => $workspace,
            'organizations' => $workspace->organizations()->active()->orderBy('name')->get(),
            'programs' => $workspace->trainingPrograms()->orderBy('name')->get(),
            'versions' => CompetencyFramework::query()
                ->with(['versions' => fn ($q) => $q->where('status', 'published')])
                ->get()
                ->flatMap->versions,
            'users' => $workspace->users()->wherePivot('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('manageInternships', $workspace);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'trainee_id' => ['required', 'exists:users,id'],
            'host_organization_id' => ['required', 'uuid'],
            'training_program_id' => ['nullable', 'uuid'],
            'framework_version_id' => [
                'required',
                'uuid',
                'exists:framework_versions,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $version = FrameworkVersion::query()->find($value);

                    if ($version !== null && $version->status !== 'published') {
                        $fail('Le stage doit être lié à une version de référentiel publiée.');
                    }
                },
            ],
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

        return view('internships.show', [
            'workspace' => $workspace,
            'internship' => $internship->load(['trainee', 'hostOrganization', 'trainingProgram', 'frameworkVersion', 'participants.user', 'projects']),
        ]);
    }

    public function edit(Workspace $workspace, string $internship): View
    {
        $internship = $this->resolve($workspace, $internship);

        $this->authorizeManage($workspace, $internship);

        return view('internships.edit', [
            'workspace' => $workspace,
            'internship' => $internship,
            'organizations' => $workspace->organizations()->active()->orderBy('name')->get(),
            'programs' => $workspace->trainingPrograms()->orderBy('name')->get(),
            'versions' => CompetencyFramework::query()
                ->with(['versions' => fn ($q) => $q->where('status', 'published')])
                ->get()
                ->flatMap->versions,
        ]);
    }

    public function update(Request $request, Workspace $workspace, string $internship): RedirectResponse
    {
        $internship = $this->resolve($workspace, $internship);

        $this->authorizeManage($workspace, $internship);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'host_organization_id' => ['required', 'uuid'],
            'training_program_id' => ['nullable', 'uuid'],
            'framework_version_id' => [
                'required',
                'uuid',
                'exists:framework_versions,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $version = FrameworkVersion::query()->find($value);

                    if ($version !== null && $version->status !== 'published') {
                        $fail('Le stage doit être lié à une version de référentiel publiée.');
                    }
                },
            ],
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
            'role' => ['required', 'string', 'in:trainee,supervisor,tutor'],
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
        $participantModel->delete();

        return redirect()
            ->route('workspace.internships.show', [$workspace, $internship])
            ->with('status', 'Participant retiré.');
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
