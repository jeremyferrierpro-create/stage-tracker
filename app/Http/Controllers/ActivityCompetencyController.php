<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Authorization\EffectivePermissions;
use App\Domain\Evaluation\Models\ActivityCompetency;
use App\Domain\Evaluation\Models\CompetencyAssessment;
use App\Domain\Framework\Models\Competency;
use App\Domain\Identity\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Associations activité ↔ compétence (T-060) et évaluations nominatives
 * (T-061, matrice rôles-permissions §3) :
 * - associer / documenter / retirer : gestionnaires de l'activité
 *   (owner/admin/stagiaire — `create_activity`) ;
 * - émettre une appréciation : maître de stage / tuteur rattachés actifs
 *   (`assess`) ; le stagiaire ne peut jamais s'auto-évaluer ;
 * - INV-09 : aucun statut n'évolue sans action explicite habilitée.
 */
class ActivityCompetencyController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function store(Request $request, Workspace $workspace, string $internship, string $project, string $activity): RedirectResponse
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);
        $projectModel = $internshipModel->projects()->findOrFail($project);
        $activityModel = $projectModel->activities()->findOrFail($activity);

        $permissions = EffectivePermissions::resolveForInternship($request->user(), $workspace, $internshipModel);
        abort_unless($permissions['create_activity'], 403);

        $validated = $request->validate([
            'competency_id' => ['required', 'uuid'],
            'justification' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        // La compétence doit appartenir à la version du référentiel du stage.
        $competency = Competency::query()
            ->where('framework_version_id', $internshipModel->framework_version_id)
            ->findOrFail($validated['competency_id']);

        $exists = ActivityCompetency::query()
            ->where('activity_id', $activityModel->id)
            ->where('competency_id', $competency->id)
            ->exists();

        if ($exists) {
            return redirect()
                ->back()
                ->withErrors(['competency_id' => 'Cette compétence est déjà associée à cette activité.']);
        }

        $association = ActivityCompetency::query()->create([
            'activity_id' => $activityModel->id,
            'workspace_id' => $workspace->id,
            'competency_id' => $competency->id,
            'justification' => $validated['justification'],
            'status' => 'mobilized',
        ]);

        $this->audit->record(
            'competency.associated',
            $workspace,
            $association,
            ['status' => 'mobilized', 'competency_code' => $competency->code],
            $request->user(),
        );

        return redirect()
            ->back()
            ->with('status', 'Compétence associée (statut : mobilisée).');
    }

    public function document(Request $request, Workspace $workspace, string $internship, string $project, string $activity, string $competency): RedirectResponse
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);
        $projectModel = $internshipModel->projects()->findOrFail($project);
        $activityModel = $projectModel->activities()->findOrFail($activity);

        $permissions = EffectivePermissions::resolveForInternship($request->user(), $workspace, $internshipModel);
        abort_unless($permissions['create_activity'], 403);

        $association = $activityModel->activityCompetencies()->findOrFail($competency);

        if ($association->status !== 'mobilized') {
            return redirect()
                ->back()
                ->withErrors(['competency' => 'Seule une compétence mobilisée peut être marquée documentée.']);
        }

        $association->forceFill([
            'status' => 'documented',
            'documented_at' => now(),
        ])->save();

        $this->audit->record(
            'competency.documented',
            $workspace,
            $association,
            ['status' => 'documented', 'competency_code' => $association->competency->code],
            $request->user(),
        );

        return redirect()
            ->back()
            ->with('status', 'Compétence marquée documentée.');
    }

    public function assess(Request $request, Workspace $workspace, string $internship, string $project, string $activity, string $competency): RedirectResponse
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);
        $projectModel = $internshipModel->projects()->findOrFail($project);
        $activityModel = $projectModel->activities()->findOrFail($activity);

        $permissions = EffectivePermissions::resolveForInternship($request->user(), $workspace, $internshipModel);
        abort_unless($permissions['assess'], 403);

        $association = $activityModel->activityCompetencies()->findOrFail($competency);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', CompetencyAssessment::STATUSES)],
            'appreciation' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $assessment = CompetencyAssessment::query()->create([
            'activity_competency_id' => $association->id,
            'assessor_id' => $request->user()->id,
            'appreciation' => $validated['appreciation'],
            'status' => $validated['status'],
            'assessed_at' => now(),
        ]);

        // Unique chemin vers `evaluated` : appréciation validée par un
        // évaluateur habilité (INV-09 / AC-11.1).
        if ($validated['status'] === 'validated') {
            $association->forceFill(['status' => 'evaluated'])->save();
        }

        $this->audit->record(
            'assessment.created',
            $workspace,
            $assessment,
            ['status' => $validated['status'], 'competency_code' => $association->competency->code],
            $request->user(),
        );

        return redirect()
            ->back()
            ->with('status', 'Appréciation enregistrée (provenance tracée).');
    }

    public function destroy(Workspace $workspace, string $internship, string $project, string $activity, string $competency): RedirectResponse
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);
        $projectModel = $internshipModel->projects()->findOrFail($project);
        $activityModel = $projectModel->activities()->findOrFail($activity);

        $permissions = EffectivePermissions::resolveForInternship(auth()->user(), $workspace, $internshipModel);
        abort_unless($permissions['create_activity'], 403);

        $association = $activityModel->activityCompetencies()->findOrFail($competency);

        // Une compétence évaluée est un fait tracé : on ne la retire plus.
        if ($association->status === 'evaluated') {
            return redirect()
                ->back()
                ->withErrors(['competency' => 'Une compétence évaluée ne peut pas être retirée (historique conservé).']);
        }

        $code = $association->competency->code;
        $association->delete();

        $this->audit->record(
            'competency.unlinked',
            $workspace,
            $activityModel,
            ['competency_code' => $code],
            auth()->user(),
        );

        return redirect()
            ->back()
            ->with('status', 'Association retirée.');
    }

    /**
     * Matrice des compétences du stage (T-060, sortie attendue).
     */
    public function matrix(Request $request, Workspace $workspace, string $internship): View
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);

        $permissions = EffectivePermissions::resolveForInternship($request->user(), $workspace, $internshipModel);
        abort_unless($permissions['view_activities'], 403);

        $rows = ActivityCompetency::query()
            ->where('workspace_id', $workspace->id)
            ->whereHas('activity', fn ($query) => $query->whereIn('activities.id', $internshipModel->projects()->with('activities')->get()->flatMap->activities->pluck('id')))
            ->with(['competency.block', 'activity.project', 'assessments.assessor'])
            ->get()
            ->sortBy(fn (ActivityCompetency $row): string => $row->competency->code)
            ->values();

        return view('internships.competencies', [
            'workspace' => $workspace,
            'internship' => $internshipModel,
            'rows' => $rows,
        ]);
    }
}
