<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Framework\FrameworkVersionService;
use App\Domain\Framework\Models\CompetencyBlock;
use App\Domain\Framework\Models\FrameworkVersion;
use App\Http\Requests\Framework\CompetencyBlockRequest;
use Illuminate\Http\RedirectResponse;

class FrameworkBlockController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly FrameworkVersionService $versionService,
    ) {}

    public function store(CompetencyBlockRequest $request, FrameworkVersion $version): RedirectResponse
    {
        $this->authorize('manage', $version->framework);

        if ($version->isPublished()) {
            abort(403);
        }

        $block = CompetencyBlock::query()->create([
            'framework_version_id' => $version->getKey(),
            'code' => $request->validated('code'),
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'position' => $request->validated('position', 0),
        ]);

        $this->audit->record(
            'framework.block.created',
            null,
            $block,
            [
                'framework_id' => $version->framework_id,
                'framework_name' => $version->framework->name,
                'version' => $version->version,
            ],
            $request->user(),
        );

        return redirect()
            ->route('frameworks.show', $version->framework)
            ->with('status', 'Bloc créé.');
    }

    public function destroy(FrameworkVersion $version, CompetencyBlock $block): RedirectResponse
    {
        $this->authorize('manage', $version->framework);

        if ($version->isPublished()) {
            abort(403);
        }

        if ($block->framework_version_id !== $version->getKey()) {
            abort(404);
        }

        $block->delete();

        return redirect()
            ->route('frameworks.show', $version->framework)
            ->with('status', 'Bloc supprimé.');
    }
}
