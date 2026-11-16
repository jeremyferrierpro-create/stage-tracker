<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Authorization\EffectivePermissions;
use App\Domain\Collaboration\Models\Review;
use App\Domain\Identity\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Revues d'activité (T-051). La demande est faite par ceux qui gèrent
 * l'activité (owner/admin/stagiaire du stage) ; le suivi est réservé au
 * réviseur (participant supervisor/tutor actif). Transitions contrôlées,
 * chaque étape est journalisée (notifications via le journal d'audit).
 */
class ReviewController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function store(Request $request, Workspace $workspace, string $internship, string $project, string $activity): RedirectResponse
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);
        $projectModel = $internshipModel->projects()->findOrFail($project);
        $activityModel = $projectModel->activities()->findOrFail($activity);

        $permissions = EffectivePermissions::resolveForInternship($request->user(), $workspace, $internshipModel);

        // Demander une revue = gérer l'activité.
        abort_unless($permissions['create_activity'], 403);

        $validated = $request->validate([
            'reviewer_id' => ['required', 'integer'],
        ]);

        $reviewer = $internshipModel->participants()
            ->where('user_id', $validated['reviewer_id'])
            ->where('status', 'active')
            ->whereIn('role', ['supervisor', 'tutor'])
            ->first();

        if ($reviewer === null) {
            return redirect()
                ->back()
                ->withErrors(['reviewer_id' => 'Le réviseur doit être un maître de stage ou un tuteur actif du stage.']);
        }

        $review = Review::query()->create([
            'activity_id' => $activityModel->getKey(),
            'workspace_id' => $workspace->getKey(),
            'reviewer_id' => $validated['reviewer_id'],
            'status' => 'requested',
        ]);

        $this->audit->record(
            'review.requested',
            $workspace,
            $review,
            [
                'subject' => $activityModel->title,
                'status' => 'requested',
            ],
            $request->user(),
        );

        return redirect()->back()->with('status', 'Revue demandée.');
    }

    public function feedback(Request $request, Workspace $workspace, string $internship, string $project, string $activity, string $review): RedirectResponse
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);
        $projectModel = $internshipModel->projects()->findOrFail($project);
        $activityModel = $projectModel->activities()->findOrFail($activity);
        $reviewModel = $activityModel->reviews()->findOrFail($review);

        $permissions = EffectivePermissions::resolveForInternship($request->user(), $workspace, $internshipModel);

        if (! $permissions['review'] || $reviewModel->reviewer_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', Review::STATUSES)],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        if (! $reviewModel->canTransitionTo($validated['status'])) {
            return redirect()
                ->back()
                ->withErrors(['review' => 'Transition de statut non autorisée ('.$reviewModel->status.' → '.$validated['status'].').']);
        }

        $from = $reviewModel->status;

        $reviewModel->forceFill([
            'status' => $validated['status'],
            'feedback' => $validated['feedback'] ?? $reviewModel->feedback,
        ])->save();

        $this->audit->record(
            'review.updated',
            $workspace,
            $reviewModel,
            [
                'subject' => $activityModel->title,
                'from' => $from,
                'to' => $validated['status'],
            ],
            $request->user(),
        );

        return redirect()->back()->with('status', 'Revue mise à jour.');
    }
}
