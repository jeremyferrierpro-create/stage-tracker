<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Evaluation\Models\Competency;
use App\Domain\Identity\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gestion simple des compétences du workspace (simplification 1.6.0) :
 * une liste définie directement par les administrateurs, rattachable aux
 * activités et évaluable par le maître de stage / tuteur.
 */
class CompetencyController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, Workspace $workspace): View
    {
        $this->authorize('manageMembers', $workspace);

        return view('competencies.index', [
            'workspace' => $workspace,
            'competencies' => Competency::query()
                ->where('workspace_id', $workspace->id)
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function create(Request $request, Workspace $workspace): View
    {
        $this->authorize('manageMembers', $workspace);

        return view('competencies.create', ['workspace' => $workspace]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $exists = Competency::query()
            ->where('workspace_id', $workspace->id)
            ->where('code', $validated['code'])
            ->exists();

        if ($exists) {
            return redirect()->back()->withErrors(['code' => 'Ce code est déjà utilisé dans cet espace.']);
        }

        $competency = Competency::query()->create($validated + ['workspace_id' => $workspace->id]);

        $this->audit->record(
            'competency.created',
            $workspace,
            $competency,
            ['competency_code' => $competency->code],
            $request->user(),
        );

        return redirect()
            ->route('workspace.competencies.index', $workspace)
            ->with('status', 'Compétence créée.');
    }

    public function destroy(Request $request, Workspace $workspace, string $competency): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        $competencyModel = Competency::query()->where('workspace_id', $workspace->id)->findOrFail($competency);

        if ($competencyModel->associations()->exists()) {
            return redirect()
                ->back()
                ->withErrors(['competency' => 'Cette compétence est associée à des activités : elle ne peut pas être supprimée.']);
        }

        $code = $competencyModel->code;
        $competencyModel->delete();

        $this->audit->record(
            'competency.deleted',
            $workspace,
            $competencyModel,
            ['competency_code' => $code],
            $request->user(),
        );

        return redirect()
            ->route('workspace.competencies.index', $workspace)
            ->with('status', 'Compétence supprimée.');
    }
}
