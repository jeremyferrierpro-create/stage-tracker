<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Organization\Models\Organization;
use App\Http\Requests\Organization\OrganizationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * CRUD des organisations d'accueil et organismes de formation (T-008).
 * Aucune suppression physique : archivage uniquement (MLD §16).
 */
class OrganizationController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Workspace $workspace): View
    {
        $this->authorize('view', $workspace);

        return view('organizations.index', [
            'workspace' => $workspace,
            'organizations' => $workspace->organizations()->active()->orderBy('name')->get(),
            'archivedOrganizations' => $workspace->organizations()
                ->archived()
                ->orderByDesc('archived_at')
                ->get(),
        ]);
    }

    public function create(Workspace $workspace): View
    {
        $this->authorize('manageOrganizations', $workspace);

        return view('organizations.create', [
            'workspace' => $workspace,
        ]);
    }

    public function store(OrganizationRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('manageOrganizations', $workspace);

        $organization = $workspace->organizations()->create($request->validated());

        $this->audit->record(
            'organization.created',
            $workspace,
            $organization,
            [
                'organization_id' => $organization->id,
                'organization_name' => $organization->name,
                'organization_type' => $organization->type,
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.organizations.index', $workspace)
            ->with('status', 'Organisation créée.');
    }

    public function edit(Workspace $workspace, string $organization): View
    {
        $this->authorize('manageOrganizations', $workspace);

        return view('organizations.edit', [
            'workspace' => $workspace,
            'organization' => $this->resolve($workspace, $organization),
        ]);
    }

    public function update(OrganizationRequest $request, Workspace $workspace, string $organization): RedirectResponse
    {
        $this->authorize('manageOrganizations', $workspace);

        $organization = $this->resolve($workspace, $organization);
        $organization->fill($request->validated());
        $changed = array_keys($organization->getDirty());
        $organization->save();

        $this->audit->record(
            'organization.updated',
            $workspace,
            $organization,
            [
                'organization_id' => $organization->id,
                'organization_name' => $organization->name,
                'organization_type' => $organization->type,
                'changed' => implode(',', $changed),
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.organizations.index', $workspace)
            ->with('status', 'Organisation mise à jour.');
    }

    public function archive(Workspace $workspace, string $organization): RedirectResponse
    {
        $this->authorize('manageOrganizations', $workspace);

        $organization = $this->resolve($workspace, $organization);

        if (! $organization->isArchived()) {
            $organization->forceFill(['archived_at' => now()])->save();

            $this->audit->record(
                'organization.archived',
                $workspace,
                $organization,
                [
                    'organization_id' => $organization->id,
                    'organization_name' => $organization->name,
                    'organization_type' => $organization->type,
                ],
                request()->user(),
            );
        }

        return redirect()
            ->route('workspace.organizations.index', $workspace)
            ->with('status', 'Organisation archivée.');
    }

    private function resolve(Workspace $workspace, string $organization): Organization
    {
        return $workspace->organizations()->findOrFail($organization);
    }
}
