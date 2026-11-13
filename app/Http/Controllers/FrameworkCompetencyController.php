<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Framework\FrameworkVersionService;
use App\Domain\Framework\Models\Competency;
use App\Domain\Framework\Models\FrameworkVersion;
use App\Http\Requests\Framework\CompetencyRequest;
use Illuminate\Http\RedirectResponse;

class FrameworkCompetencyController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly FrameworkVersionService $versionService,
    ) {}

    public function store(CompetencyRequest $request, FrameworkVersion $version): RedirectResponse
    {
        $this->authorize('manage', $version->framework);

        if ($version->isPublished()) {
            abort(403);
        }

        $competency = Competency::query()->create([
            'framework_version_id' => $version->getKey(),
            'block_id' => $request->validated('block_id'),
            'parent_id' => $request->validated('parent_id'),
            'code' => $request->validated('code'),
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'position' => $request->validated('position', 0),
        ]);

        $this->audit->record(
            'framework.competency.created',
            null,
            $competency,
            [
                'framework_id' => $version->framework_id,
                'framework_name' => $version->framework->name,
                'version' => $version->version,
            ],
            $request->user(),
        );

        return redirect()
            ->route('frameworks.show', $version->framework)
            ->with('status', 'CompÃ©tence crÃ©Ã©e.');
    }

    public function destroy(FrameworkVersion $version, Competency $competency): RedirectResponse
    {
        $this->authorize('manage', $version->framework);

        if ($version->isPublished()) {
            abort(403);
        }

        if ($competency->framework_version_id !== $version->getKey()) {
            abort(404);
        }

        $competency->delete();

        return redirect()
            ->route('frameworks.show', $version->framework)
            ->with('status', 'CompÃ©tence supprimÃ©e.');
    }
}
