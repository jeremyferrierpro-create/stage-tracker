<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Framework\Models\CompetencyFramework;
use App\Http\Requests\Framework\CompetencyFrameworkRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FrameworkController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $this->authorize('view', new CompetencyFramework);

        return view('frameworks.index', [
            'frameworks' => CompetencyFramework::query()
                ->withCount('versions')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage', new CompetencyFramework);

        return view('frameworks.create');
    }

    public function store(CompetencyFrameworkRequest $request): RedirectResponse
    {
        $this->authorize('manage', new CompetencyFramework);

        $framework = CompetencyFramework::query()->create($request->validated());

        $this->audit->record(
            'framework.created',
            null,
            $framework,
            [
                'framework_id' => $framework->getKey(),
                'framework_name' => $framework->name,
            ],
            $request->user(),
        );

        return redirect()
            ->route('frameworks.show', $framework)
            ->with('status', 'Référentiel créé.');
    }

    public function show(CompetencyFramework $framework): View
    {
        $this->authorize('view', $framework);

        return view('frameworks.show', [
            'framework' => $framework->load(['versions.blocks', 'versions.competencies.block', 'versions.competencies.criteria']),
        ]);
    }

    public function edit(CompetencyFramework $framework): View
    {
        $this->authorize('manage', $framework);

        return view('frameworks.edit', [
            'framework' => $framework,
        ]);
    }

    public function update(CompetencyFrameworkRequest $request, CompetencyFramework $framework): RedirectResponse
    {
        $this->authorize('manage', $framework);

        $framework->fill($request->validated());
        $changed = array_keys($framework->getDirty());
        $framework->save();

        $this->audit->record(
            'framework.updated',
            null,
            $framework,
            [
                'framework_id' => $framework->getKey(),
                'framework_name' => $framework->name,
                'changed' => implode(',', $changed),
            ],
            $request->user(),
        );

        return redirect()
            ->route('frameworks.show', $framework)
            ->with('status', 'Référentiel mis à jour.');
    }
}
