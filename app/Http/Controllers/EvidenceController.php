<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Evidence\Models\ActivityEvidence;
use App\Domain\Evidence\Models\Evidence;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Project\Models\Activity;
use App\Integrations\Storage\EvidenceStorageInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Preuves et visibilité (T-040/T-041/T-042). Upload sécurisé (validation
 * MIME/taille, noms aléatoires, hors webroot), téléchargement contrôlé
 * (policy `view`), visibilité private/stage/shared.
 */
class EvidenceController extends Controller
{
    private const UPLOAD_MIMES = 'png,jpg,jpeg,webp,gif,svg,pdf,txt,md,csv,doc,docx,odt,xls,xlsx,zip';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EvidenceStorageInterface $storage,
    ) {}

    public function index(Workspace $workspace): View
    {
        $this->authorize('view', $workspace);

        $evidences = $workspace->evidences()
            ->with('owner')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (Evidence $evidence): bool => Gate::forUser(auth()->user())->allows('view', $evidence));

        return view('evidences.index', [
            'workspace' => $workspace,
            'evidences' => $evidences,
        ]);
    }

    public function create(Workspace $workspace): View
    {
        $this->authorize('view', $workspace);

        return view('evidences.create', [
            'workspace' => $workspace,
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('view', $workspace);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', 'string', 'in:'.implode(',', Evidence::TYPES)],
            'description' => ['nullable', 'string', 'max:5000'],
            'demonstrated' => ['required', 'string', 'max:5000'],
            'visibility' => ['required', 'string', 'in:private,stage,shared'],
            'external_url' => ['nullable', 'url', 'max:500'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:'.self::UPLOAD_MIMES],
            'captured_at' => ['nullable', 'date'],
        ]);

        if (empty($validated['external_url']) && ! $request->hasFile('file')) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['file' => 'Un fichier ou un lien externe est requis.']);
        }

        $evidenceId = (string) Str::uuid();

        $storageKey = $request->hasFile('file')
            ? $this->storage->store($request->file('file'), $workspace->getKey(), $evidenceId)
            : null;

        $evidence = Evidence::query()->create([
            'id' => $evidenceId,
            'workspace_id' => $workspace->getKey(),
            'owner_id' => $request->user()->id,
            'title' => $validated['title'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'demonstrated' => $validated['demonstrated'],
            'visibility' => $validated['visibility'],
            'storage_key' => $storageKey,
            'external_url' => $validated['external_url'] ?? null,
            'captured_at' => $validated['captured_at'] ?? null,
        ]);

        $this->audit->record(
            'evidence.created',
            $workspace,
            $evidence,
            [
                'subject' => $evidence->title,
                'status' => $evidence->visibility,
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.evidences.index', $workspace)
            ->with('status', 'Preuve créée.');
    }

    public function show(Workspace $workspace, string $evidence): View
    {
        $evidence = $this->resolve($workspace, $evidence);

        $this->authorize('view', $evidence);

        return view('evidences.show', [
            'workspace' => $workspace,
            'evidence' => $evidence->load('owner', 'activities'),
        ]);
    }

    public function download(Workspace $workspace, string $evidence)
    {
        $evidence = $this->resolve($workspace, $evidence);

        $this->authorize('view', $evidence);

        if ($evidence->storage_key === null) {
            abort(404, 'Aucun fichier associé à cette preuve.');
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($evidence->storage_key)) {
            abort(404, 'Fichier introuvable sur le stockage.');
        }

        $extension = pathinfo($evidence->storage_key, PATHINFO_EXTENSION);
        $downloadName = preg_replace('/[^A-Za-z0-9_-]+/', '-', $evidence->title).'.'.$extension;

        return $disk->download($evidence->storage_key, $downloadName);
    }

    public function edit(Workspace $workspace, string $evidence): View
    {
        $evidence = $this->resolve($workspace, $evidence);

        $this->authorize('manage', $evidence);

        return view('evidences.edit', [
            'workspace' => $workspace,
            'evidence' => $evidence,
        ]);
    }

    public function update(Request $request, Workspace $workspace, string $evidence): RedirectResponse
    {
        $evidence = $this->resolve($workspace, $evidence);

        $this->authorize('manage', $evidence);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', 'string', 'in:'.implode(',', Evidence::TYPES)],
            'description' => ['nullable', 'string', 'max:5000'],
            'demonstrated' => ['required', 'string', 'max:5000'],
            'visibility' => ['required', 'string', 'in:private,stage,shared'],
            'external_url' => ['nullable', 'url', 'max:500'],
            'captured_at' => ['nullable', 'date'],
        ]);

        $evidence->fill($validated);
        $changed = array_keys($evidence->getDirty());
        $evidence->save();

        $this->audit->record(
            'evidence.updated',
            $workspace,
            $evidence,
            [
                'subject' => $evidence->title,
                'status' => $evidence->visibility,
                'changed' => implode(',', $changed),
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.evidences.show', [$workspace, $evidence])
            ->with('status', 'Preuve mise à jour.');
    }

    public function archive(Workspace $workspace, string $evidence): RedirectResponse
    {
        $evidence = $this->resolve($workspace, $evidence);

        $this->authorize('manage', $evidence);

        if ($evidence->archived_at === null) {
            $evidence->forceFill(['archived_at' => now()])->save();

            $this->audit->record(
                'evidence.archived',
                $workspace,
                $evidence,
                [
                    'subject' => $evidence->title,
                    'status' => $evidence->visibility,
                ],
                auth()->user(),
            );
        }

        return redirect()
            ->route('workspace.evidences.index', $workspace)
            ->with('status', 'Preuve archivée.');
    }

    public function linkActivity(Request $request, Workspace $workspace, string $evidence): RedirectResponse
    {
        $evidence = $this->resolve($workspace, $evidence);

        $this->authorize('view', $evidence);

        $validated = $request->validate([
            'activity_id' => ['required', 'uuid', 'exists:activities,id'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $activity = Activity::query()
            ->where('workspace_id', $workspace->getKey())
            ->findOrFail($validated['activity_id']);

        ActivityEvidence::query()->firstOrCreate(
            [
                'activity_id' => $activity->getKey(),
                'evidence_id' => $evidence->getKey(),
            ],
            [
                'workspace_id' => $workspace->getKey(),
                'note' => $validated['note'] ?? null,
            ],
        );

        $this->audit->record(
            'evidence.linked',
            $workspace,
            $evidence,
            [
                'subject' => $evidence->title,
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.evidences.show', [$workspace, $evidence])
            ->with('status', 'Preuve liée à l\'activité.');
    }

    private function resolve(Workspace $workspace, string $evidence): Evidence
    {
        return $workspace->evidences()->findOrFail($evidence);
    }
}
