<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Training\Models\TrainingEnrollment;
use App\Http\Requests\Training\TrainingEnrollmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class TrainingEnrollmentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function store(TrainingEnrollmentRequest $request, Workspace $workspace, string $program): RedirectResponse
    {
        $this->authorize('manageTraining', $workspace);

        $program = $workspace->trainingPrograms()->findOrFail($program);

        $enrollment = TrainingEnrollment::query()->create([
            'user_id' => $request->validated('user_id'),
            'training_program_id' => $program->getKey(),
            'workspace_id' => $workspace->getKey(),
            'start_date' => $request->validated('start_date'),
            'end_date' => $request->validated('end_date'),
            'status' => 'active',
        ]);

        $this->audit->record(
            'enrollment.created',
            $workspace,
            $enrollment,
            [
                'enrollment_id' => $enrollment->getKey(),
                'training_program_id' => $program->getKey(),
                'training_program_name' => $program->name,
                'status' => 'active',
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.training.edit', [$workspace, $program])
            ->with('status', 'Inscription ajoutée.');
    }

    public function updateStatus(TrainingEnrollmentRequest $request, Workspace $workspace, string $program, string $enrollment): RedirectResponse
    {
        $this->authorize('manageTraining', $workspace);

        $program = $workspace->trainingPrograms()->findOrFail($program);
        $enrollment = $program->enrollments()->findOrFail($enrollment);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(TrainingEnrollment::STATUSES)],
        ]);

        $newStatus = $validated['status'];
        $from = $enrollment->status;

        $enrollment->fill(['status' => $newStatus])->save();

        $this->audit->record(
            'enrollment.updated',
            $workspace,
            $enrollment,
            [
                'enrollment_id' => $enrollment->getKey(),
                'training_program_id' => $program->getKey(),
                'training_program_name' => $program->name,
                'from' => $from,
                'to' => $newStatus,
                'status' => $newStatus,
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.training.edit', [$workspace, $program])
            ->with('status', 'Statut de l\'inscription mis à jour.');
    }
}
