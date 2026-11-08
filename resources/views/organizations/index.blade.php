@extends('layouts.app')

@section('title', 'Organisations — '.$workspace->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Organisations'],
    ]"/>
@endsection

@section('content')
    <h1>Organisations de {{ $workspace->name }}</h1>

    <p class="muted">
        Les organisations d'accueil (entreprises, associations) et les organismes de
        formation sont distingués par leur type. L'archivage conserve l'historique.
    </p>

    @can('manageOrganizations', $workspace)
        <p>
            <a class="button" href="{{ route('workspace.organizations.create', $workspace) }}">
                Nouvelle organisation
            </a>
        </p>
    @endcan

    <div class="card">
        <h2>Organisations actives</h2>

        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Type</th>
                    <th>Contact</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($organizations as $organization)
                    <tr>
                        <td>{{ $organization->name }}</td>
                        <td><span class="badge">{{ $organization->typeLabel() }}</span></td>
                        <td>
                            {{ $organization->contact_email ?? '—' }}<br>
                            <span class="muted">{{ $organization->contact_phone }}</span>
                        </td>
                        <td>
                            @can('manageOrganizations', $workspace)
                                <div class="actions">
                                    <a class="button secondary small"
                                       href="{{ route('workspace.organizations.edit', [$workspace, $organization]) }}">
                                        Modifier
                                    </a>

                                    <form method="POST"
                                          action="{{ route('workspace.organizations.archive', [$workspace, $organization]) }}">
                                        @csrf
                                        <button type="submit" class="link">Archiver</button>
                                    </form>
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">Aucune organisation active.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($archivedOrganizations->isNotEmpty())
        <div class="card">
            <h2>Organisations archivées</h2>

            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Type</th>
                        <th>Archivée le</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($archivedOrganizations as $organization)
                        <tr>
                            <td>{{ $organization->name }}</td>
                            <td>{{ $organization->typeLabel() }}</td>
                            <td>{{ $organization->archived_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
