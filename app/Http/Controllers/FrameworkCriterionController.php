<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Framework\FrameworkVersionService;
use App\Domain\Framework\Models\Competency;
use App\Domain\Framework\Models\CompetencyCriterion;
use App\Http\Requests\Framework\CompetencyCriterionRequest;
use Illuminate\Http\RedirectResponse;

class FrameworkCriterionController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly FrameworkVersionService $versionService,
    ) {}

    public function store(CompetencyCriterionRequest $request, Competency $competency): RedirectResponse
    {
        $this->authorize('manage', $competency->version->framework);

        if ($competency->version->isPublished()) {
            abort(403);
        }

        $criterion = CompetencyCriterion::query()->create([
            'competency_id' => $competency->getKey(),
            'label' => $request->validated('label'),
            'description' => $request->validated('description'),
            'position' => $request->validated('position', 0),
        ]);

        $this->audit->record(
            'framework.criterion.created',
            null,
            $criterion,
            [
                'framework_id' => $competency->version->framework_id,
                'framework_name' => $competency->version->framework->name,
                'version' => $competency->version->version,
            ],
            $request->user(),
        );

        return redirect()
            ->route('frameworks.show', $competency->version->framework)
            ->with('status', 'CritÃ¨re ajoutÃ©.');
    }

    public function destroy(Competency $competency, CompetencyCriterion $criterion): RedirectResponse
    {
        $this->authorize('manage', $competency->version->framework);

        if ($competency->version->isPublished()) {
            abort(403);
        }

        if ($criterion->competency_id !== $competency->getKey()) {
            abort(404);
        }

        $criterion->delete();

        return redirect()
            ->route('frameworks.show', $competency->version->framework)
            ->with('status', 'CritÃ¨re supprimÃ©.');
    }
}
