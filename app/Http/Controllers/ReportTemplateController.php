<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Reporting\Models\ReportTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Modèles de rapports (T-062) : CRUD réservé aux administrateurs de
 * l'espace (owner/admin), trame `schema_json` validée (aucune trame codée
 * en dur), publication et immuabilité des versions publiées.
 */
class ReportTemplateController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, Workspace $workspace): View
    {
        $this->authorize('manageMembers', $workspace);

        return view('report-templates.index', [
            'workspace' => $workspace,
            'templates' => ReportTemplate::query()
                ->where('workspace_id', $workspace->id)
                ->with('versions')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(Request $request, Workspace $workspace): View
    {
        $this->authorize('manageMembers', $workspace);

        return view('report-templates.create', ['workspace' => $workspace]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        $validated = $this->validated($request);

        $template = ReportTemplate::query()->create($validated + ['workspace_id' => $workspace->id]);

        $this->audit->record('report_template.created', $workspace, $template, ['status' => 'draft'], $request->user());

        return redirect()
            ->route('workspace.report-templates.show', [$workspace, $template])
            ->with('status', 'Modèle de rapport créé (brouillon).');
    }

    public function show(Request $request, Workspace $workspace, string $template): View
    {
        $this->authorize('manageMembers', $workspace);

        $templateModel = ReportTemplate::query()
            ->where('workspace_id', $workspace->id)
            ->with('versions')
            ->findOrFail($template);

        return view('report-templates.show', [
            'workspace' => $workspace,
            'template' => $templateModel,
            'versions' => $templateModel->versions()->orderByDesc('version')->get(),
        ]);
    }

    public function edit(Request $request, Workspace $workspace, string $template): View
    {
        $this->authorize('manageMembers', $workspace);

        $templateModel = ReportTemplate::query()->where('workspace_id', $workspace->id)->findOrFail($template);

        return view('report-templates.edit', [
            'workspace' => $workspace,
            'template' => $templateModel,
        ]);
    }

    public function update(Request $request, Workspace $workspace, string $template): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        $templateModel = ReportTemplate::query()->where('workspace_id', $workspace->id)->findOrFail($template);

        $templateModel->update($this->validated($request));

        $this->audit->record('report_template.updated', $workspace, $templateModel, [], $request->user());

        return redirect()
            ->route('workspace.report-templates.show', [$workspace, $templateModel])
            ->with('status', 'Modèle mis à jour.');
    }

    public function destroy(Request $request, Workspace $workspace, string $template): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        $templateModel = ReportTemplate::query()->where('workspace_id', $workspace->id)->findOrFail($template);

        if ($templateModel->status === 'published') {
            return redirect()
                ->back()
                ->withErrors(['template' => 'Un modèle publié ne peut pas être supprimé (archivez-le).']);
        }

        $templateModel->delete();

        $this->audit->record('report_template.deleted', $workspace, $templateModel, [], $request->user());

        return redirect()
            ->route('workspace.report-templates.index', $workspace)
            ->with('status', 'Modèle supprimé.');
    }

    public function storeVersion(Request $request, Workspace $workspace, string $template): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        $templateModel = ReportTemplate::query()->where('workspace_id', $workspace->id)->findOrFail($template);

        $validated = $request->validate([
            'version' => ['required', 'string', 'max:40'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['required', 'string', 'in:'.implode(',', ReportTemplate::ALLOWED_SECTIONS)],
            'title' => ['nullable', 'string', 'max:200'],
        ]);

        $exists = $templateModel->versions()->where('version', $validated['version'])->exists();
        if ($exists) {
            return redirect()->back()->withErrors(['version' => 'Cette version existe déjà pour ce modèle.']);
        }

        $schema = [
            'title' => $validated['title'] ?? $templateModel->name,
            'sections' => array_values($validated['sections']),
        ];

        $errors = ReportTemplate::schemaErrors($schema);
        if ($errors !== []) {
            return redirect()->back()->withErrors(['schema_json' => implode(' ', $errors)]);
        }

        $version = $templateModel->versions()->create([
            'version' => $validated['version'],
            'schema_json' => $schema,
            'published_at' => null,
        ]);

        $this->audit->record('report_template.version.created', $workspace, $version, ['version' => $validated['version']], $request->user());

        return redirect()
            ->back()
            ->with('status', 'Version de trame ajoutée (non publiée).');
    }

    public function publishVersion(Request $request, Workspace $workspace, string $template, string $version): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        $templateModel = ReportTemplate::query()->where('workspace_id', $workspace->id)->findOrFail($template);
        $versionModel = $templateModel->versions()->findOrFail($version);

        if ($versionModel->isPublished()) {
            return redirect()->back()->withErrors(['version' => 'Cette version est déjà publiée (immuable).']);
        }

        $versionModel->forceFill(['published_at' => now()])->save();
        $templateModel->forceFill(['status' => 'published'])->save();

        $this->audit->record('report_template.version.published', $workspace, $versionModel, ['version' => $versionModel->version], $request->user());

        return redirect()
            ->back()
            ->with('status', 'Version publiée — elle est désormais immuable.');
    }

    /**
     * @return array{name: string, type: string, description: string|null, status: string}
     */
    private function validated(Request $request): array
    {
        /** @var array{name: string, type: string, description?: string|null, status?: string} $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'string', 'in:'.implode(',', ReportTemplate::TYPES)],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', 'in:'.implode(',', ReportTemplate::STATUSES)],
        ]);

        return [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'draft',
        ];
    }
}
