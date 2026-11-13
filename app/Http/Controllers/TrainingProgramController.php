<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Training\Models\TrainingProgram;
use App\Http\Requests\Training\TrainingProgramRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TrainingProgramController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Workspace $workspace): View
    {
        $this->authorize('view', $workspace);

        return view('training.index', [
            'workspace' => $workspace,
            'programs' => $workspace->trainingPrograms()
                ->with('organization')
                ->withCount('enrollments')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(Workspace $workspace): View
    {
        $this->authorize('manageTraining', $workspace);

        return view('training.create', [
            'workspace' => $workspace,
            'organizations' => $workspace->organizations()->active()->orderBy('name')->get(),
        ]);
    }

    public function store(TrainingProgramRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('manageTraining', $workspace);

        $program = $workspace->trainingPrograms()->create($request->validated());

        $this->audit->record(
            'training_program.created',
            $workspace,
            $program,
            [
                'training_program_id' => $program->id,
                'training_program_name' => $program->name,
                'organization_id' => $program->organization_id,
                'organization_name' => $program->organization->name,
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.training.index', $workspace)
            ->with('status', 'Programme de formation créé.');
    }

    public function edit(Workspace $workspace, string $program): View
    {
        $this->authorize('manageTraining', $workspace);

        $program = $this->resolve($workspace, $program);

        return view('training.edit', [
            'workspace' => $workspace,
            'program' => $program,
            'organizations' => $workspace->organizations()->active()->orderBy('name')->get(),
            'enrollments' => $program->enrollments()->with('user')->orderBy('start_date')->get(),
            'candidates' => $workspace->users()->wherePivot('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function update(TrainingProgramRequest $request, Workspace $workspace, string $program): RedirectResponse
    {
        $this->authorize('manageTraining', $workspace);

        $program = $this->resolve($workspace, $program);
        $program->fill($request->validated());
        $changed = array_keys($program->getDirty());
        $program->save();

        $this->audit->record(
            'training_program.updated',
            $workspace,
            $program,
            [
                'training_program_id' => $program->id,
                'training_program_name' => $program->name,
                'changed' => implode(',', $changed),
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.training.index', $workspace)
            ->with('status', 'Programme de formation mis à jour.');
    }

    private function resolve(Workspace $workspace, string $program): TrainingProgram
    {
        return $workspace->trainingPrograms()->findOrFail($program);
    }
}
