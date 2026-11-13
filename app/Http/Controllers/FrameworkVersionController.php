<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Framework\FrameworkVersionService;
use App\Domain\Framework\Models\CompetencyFramework;
use App\Domain\Framework\Models\FrameworkVersion;
use App\Http\Requests\Framework\FrameworkVersionRequest;
use Illuminate\Http\RedirectResponse;

class FrameworkVersionController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly FrameworkVersionService $versionService,
    ) {}

    public function store(FrameworkVersionRequest $request, CompetencyFramework $framework): RedirectResponse
    {
        $this->authorize('manage', $framework);

        $version = FrameworkVersion::query()->create([
            'framework_id' => $framework->getKey(),
            'version' => $request->validated('version'),
            'effective_date' => $request->validated('effective_date'),
            'status' => 'draft',
        ]);

        $this->audit->record(
            'framework.version.created',
            null,
            $version,
            [
                'framework_id' => $framework->getKey(),
                'framework_name' => $framework->name,
                'version' => $version->version,
                'status' => 'draft',
            ],
            $request->user(),
        );

        return redirect()
            ->route('frameworks.show', ['framework' => $framework->getKey()])
            ->with('status', 'Version créée.');
    }

    public function publish(string $version): RedirectResponse
    {
        $versionModel = FrameworkVersion::findOrFail($version);
        $framework = $versionModel->framework;

        $this->authorize('manage', $framework);

        try {
            $published = $this->versionService->publish($versionModel, request()->user());
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('frameworks.show', ['framework' => $framework->getKey()])
                ->with('status', $exception->getMessage());
        }

        return redirect()
            ->route('frameworks.show', ['framework' => $framework->getKey()])
            ->with('status', 'Version « '.$published->version.' » publiée : immuable désormais.');
    }
}
