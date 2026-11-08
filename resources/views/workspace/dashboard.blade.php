@extends('layouts.app')

@section('title', $workspace->name)

@section('content')
    <div class="card">
        <h1>{{ $workspace->name }}</h1>
        <p>
            <span class="badge">{{ $workspace->typeLabel() }}</span>
            <span class="badge">{{ \App\Domain\Identity\Models\Workspace::roleLabel($membership->role) }}</span>
        </p>

        <p>
            Vous êtes membre actif de cet espace. Toutes les données affichées ici
            sont filtrées par le serveur sur ce workspace (isolation multi-tenant).
        </p>

        <p class="muted">
            Les autres modules métier (stages, référentiels, journal, preuves,
            évaluations, rapports) arrivent dans les tâches suivantes.
        </p>

        <div class="actions">
            <a class="button" href="{{ route('workspace.organizations.index', $workspace) }}">
                Organisations
            </a>

            @can('manageMembers', $workspace)
                <a class="button secondary" href="{{ route('workspace.members.index', $workspace) }}">
                    Membres et invitations
                </a>
            @endcan

            @can('viewAudit', $workspace)
                <a class="button secondary" href="{{ route('workspace.audit.index', $workspace) }}">
                    Journal d'audit
                </a>
            @endcan

            <a class="button secondary" href="{{ route('workspaces.index') }}">Changer d'espace</a>
        </div>
    </div>
@endsection
