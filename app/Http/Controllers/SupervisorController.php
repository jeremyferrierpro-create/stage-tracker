<?php

namespace App\Http\Controllers;

use App\Domain\Collaboration\Models\Review;
use App\Domain\Evidence\Models\Evidence;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Internship\Models\Internship;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Vue dédiée maître de stage / tuteur (T-054, APPFLOW §3) : périmètre
 * limité aux stages où l'utilisateur est participant actif. Aucune donnée
 * privée (preuve `private`, `private_notes` du journal) n'est sérialisée
 * dans cette vue — les preuves privées sont exclues en amont, le journal
 * n'affiche que les champs de travail.
 */
class SupervisorController extends Controller
{
    public function dashboard(Request $request, Workspace $workspace): View
    {
        $user = $request->user();

        // Stages où l'utilisateur est rattaché actif (supervisor/tutor).
        $assignments = $workspace->internships()
            ->whereHas('participants', function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->whereIn('role', ['supervisor', 'tutor']);
            })
            ->with(['trainee'])
            ->get();

        $isEligible = in_array($workspace->roleFor($user), ['supervisor', 'tutor'], true);

        if ($assignments->isEmpty() && ! $isEligible) {
            abort(403);
        }

        $panels = $assignments->map(function (Internship $internship) use ($user): array {
            $activities = $internship->projects()
                ->with('activities')
                ->get()
                ->flatMap->activities
                ->sortByDesc('created_at')
                ->values();
            $activityIds = $activities->pluck('id')->all();

            // Preuves visibles : stage + shared uniquement (jamais private).
            $evidences = Evidence::query()
                ->where('workspace_id', $internship->workspace_id)
                ->whereHas('activities', fn ($query) => $query->whereIn('activities.id', $activityIds))
                ->whereIn('visibility', ['stage', 'shared'])
                ->orderByDesc('created_at')
                ->limit(6)
                ->get();

            // Revues à traiter par cet utilisateur.
            $pendingReviews = Review::query()
                ->where('workspace_id', $internship->workspace_id)
                ->whereIn('activity_id', $activityIds)
                ->where('reviewer_id', $user->id)
                ->whereIn('status', ['requested', 'in_progress', 'changes_requested'])
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Review $review) => (object) [
                    'review' => $review,
                    'activity' => $activities->firstWhere('id', $review->activity_id),
                ]);

            return [
                'internship' => $internship,
                'role' => $internship->participants()
                    ->where('user_id', $user->id)
                    ->value('role'),
                'activities' => $activities->take(6),
                'evidences' => $evidences,
                'pendingReviews' => $pendingReviews,
                // Journal : champs de travail uniquement, sans private_notes.
                'dailyLogs' => $internship->dailyLogs()
                    ->orderByDesc('work_date')
                    ->limit(5)
                    ->get(['id', 'work_date', 'work_done', 'status', 'author_id', 'internship_id']),
            ];
        });

        return view('supervisor.dashboard', [
            'workspace' => $workspace,
            'panels' => $panels,
        ]);
    }
}
