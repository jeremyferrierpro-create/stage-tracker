<?php

namespace App\Http\Controllers;

use App\Domain\Authorization\EffectivePermissions;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Internship\Models\Internship;
use App\Domain\Planning\PlanningComparisonService;
use Illuminate\View\View;

class PlanningController extends Controller
{
    public function show(Workspace $workspace, string $internship): View
    {
        $this->authorize('view', $workspace);

        $internship = $workspace->internships()->findOrFail($internship);

        // Même règle que la lecture du journal (T-052).
        abort_unless(
            EffectivePermissions::resolveForInternship(auth()->user(), $workspace, $internship)['view_journal'],
            403,
        );

        return view('planning.show', [
            'workspace' => $workspace,
            'internship' => $internship,
            'days' => app(PlanningComparisonService::class)->daysFor($internship),
        ]);
    }
}
