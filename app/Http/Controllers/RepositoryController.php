<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Authorization\EffectivePermissions;
use App\Domain\Git\Models\ActivityCommit;
use App\Domain\Git\Models\Repository;
use App\Domain\Git\Services\CommitImportService;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Internship\Models\Internship;
use App\Integrations\Git\GitProviderInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

/**
 * Dépôts et connecteur GitHub (T-043), import idempotent (T-044) et
 * association commits ↔ activités (T-045). Le jeton, s'il est fourni, est
 * chiffré côté serveur et n'apparaît jamais dans une réponse ni dans le
 * journal d'audit.
 */
class RepositoryController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CommitImportService $importer,
        private readonly GitProviderInterface $provider,
    ) {}

    public function create(Workspace $workspace, string $internship, string $project): View
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);
        $projectModel = $internshipModel->projects()->findOrFail($project);

        $this->authorizeManage($workspace, $internshipModel);

        return view('repositories.create', [
            'workspace' => $workspace,
            'internship' => $internshipModel,
            'project' => $projectModel,
        ]);
    }

    public function store(Request $request, Workspace $workspace, string $internship, string $project): RedirectResponse
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);
        $projectModel = $internshipModel->projects()->findOrFail($project);

        $this->authorizeManage($workspace, $internshipModel);

        $provider = $request->input('provider', 'github');

        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:github,gitlab,other'],
            'external_id' => [
                'required',
                'string',
                'max:120',
                'unique:repositories,external_id,NULL,id,provider,'.$provider,
            ],
            'name' => ['required', 'string', 'max:160'],
            'repository_url' => ['required', 'url', 'max:500'],
            'default_branch' => ['nullable', 'string', 'max:120'],
            'token' => ['nullable', 'string', 'max:200'],
        ]);

        $repository = Repository::query()->create([
            'project_id' => $projectModel->getKey(),
            'workspace_id' => $workspace->getKey(),
            'provider' => $validated['provider'],
            'external_id' => $validated['external_id'],
            'name' => $validated['name'],
            'repository_url' => $validated['repository_url'],
            'default_branch' => $validated['default_branch'] ?? 'main',
            'token_encrypted' => ! empty($validated['token'])
                ? Crypt::encryptString($validated['token'])
                : null,
        ]);

        $this->audit->record(
            'repository.created',
            $workspace,
            $repository,
            [
                'subject' => $projectModel->name,
                'repository_name' => $repository->name,
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.internships.projects.repositories.show', [$workspace, $internship, $project, $repository])
            ->with('status', 'Dépôt associé. Lancez une synchronisation pour importer les commits.');
    }

    public function show(Workspace $workspace, string $internship, string $project, string $repository): View
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);
        $projectModel = $internshipModel->projects()->findOrFail($project);
        $repositoryModel = $projectModel->repositories()->findOrFail($repository);

        $this->authorize('view', $workspace);

        // T-052 : portée « stage attribué » pour supervisor/tutor/viewer.
        abort_unless(
            EffectivePermissions::resolveForInternship(auth()->user(), $workspace, $internshipModel)['view_activities'],
            403,
        );

        $commits = $repositoryModel->commits()
            ->with(['files', 'activities'])
            ->orderByDesc('committed_at')
            ->limit(50)
            ->get();

        return view('repositories.show', [
            'workspace' => $workspace,
            'internship' => $internshipModel,
            'project' => $projectModel,
            'repository' => $repositoryModel,
            'commits' => $commits,
            'syncRuns' => $repositoryModel->syncRuns()->limit(5)->get(),
            'activities' => $projectModel->activities()->orderBy('title')->get(),
        ]);
    }

    public function sync(Request $request, Workspace $workspace, string $internship, string $project, string $repository): RedirectResponse
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);
        $projectModel = $internshipModel->projects()->findOrFail($project);
        $repositoryModel = $projectModel->repositories()->findOrFail($repository);

        $this->authorizeManage($workspace, $internshipModel);

        $run = $this->importer->sync($repositoryModel);

        if ($run->status === 'failed') {
            return redirect()
                ->back()
                ->withErrors(['sync' => $run->error_summary ?? 'La synchronisation a échoué.']);
        }

        $this->audit->record(
            'repository.synced',
            $workspace,
            $repositoryModel,
            [
                'subject' => $projectModel->name,
                'repository_name' => $repositoryModel->name,
                'status' => $run->status,
                'items_imported' => $run->items_imported,
            ],
            $request->user(),
        );

        return redirect()
            ->back()
            ->with('status', $run->items_imported.' commit(s) importé(s).');
    }

    public function linkActivity(Request $request, Workspace $workspace, string $internship, string $project, string $repository, string $commit): RedirectResponse
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);
        $projectModel = $internshipModel->projects()->findOrFail($project);
        $repositoryModel = $projectModel->repositories()->findOrFail($repository);
        $commitModel = $repositoryModel->commits()->findOrFail($commit);

        $this->authorizeManage($workspace, $internshipModel);

        $validated = $request->validate([
            'activity_id' => ['required', 'uuid'],
        ]);

        $activity = $projectModel->activities()->findOrFail($validated['activity_id']);

        ActivityCommit::query()->firstOrCreate(
            [
                'activity_id' => $activity->getKey(),
                'commit_id' => $commitModel->getKey(),
            ],
            [
                'workspace_id' => $workspace->getKey(),
                'linked_by' => $request->user()->id,
                'linked_at' => now(),
            ],
        );

        $this->audit->record(
            'commit.linked',
            $workspace,
            $commitModel,
            [
                'subject' => $activity->title,
                'commit_sha' => $commitModel->shortSha(),
            ],
            $request->user(),
        );

        return redirect()
            ->back()
            ->with('status', 'Commit lié à l\'activité.');
    }

    public function destroy(Workspace $workspace, string $internship, string $project, string $repository): RedirectResponse
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);
        $projectModel = $internshipModel->projects()->findOrFail($project);
        $repositoryModel = $projectModel->repositories()->findOrFail($repository);

        $this->authorizeManage($workspace, $internshipModel);

        $name = $repositoryModel->name;
        $repositoryModel->delete();

        $this->audit->record(
            'repository.deleted',
            $workspace,
            null,
            [
                'subject' => $projectModel->name,
                'repository_name' => $name,
            ],
            auth()->user(),
        );

        return redirect()
            ->route('workspace.internships.projects.show', [$workspace, $internship, $project])
            ->with('status', 'Dépôt dissocié (commits importés supprimés avec le dépôt).');
    }

    /**
     * Test de connexion utilisé par le formulaire (optionnel).
     */
    public function testConnection(Request $request, Workspace $workspace, string $internship, string $project): RedirectResponse
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);
        $projectModel = $internshipModel->projects()->findOrFail($project);

        $this->authorizeManage($workspace, $internshipModel);

        $validated = $request->validate([
            'repository_url' => ['required', 'url', 'max:500'],
            'token' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $info = $this->provider->testConnection(
                $validated['repository_url'],
                ! empty($validated['token']) ? $validated['token'] : null,
            );

            return redirect()
                ->back()
                ->with('status', 'Connexion réussie : '.$info['name']);
        } catch (\Throwable $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['connection' => $exception->getMessage()]);
        }
    }

    private function authorizeManage(Workspace $workspace, Internship $internship): void
    {
        $user = auth()->user();
        $isManager = in_array($workspace->roleFor($user), ['owner', 'admin'], true);
        $isTrainee = $internship->trainee_id === $user?->id;

        if (! $isManager && ! $isTrainee) {
            abort(403);
        }
    }
}
