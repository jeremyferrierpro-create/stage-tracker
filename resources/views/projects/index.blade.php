@extends('layouts.app')

@section('title', 'Projets — '.$internship->title)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => 'Projets'],
    ]"/>
@endsection

@section('content')
    <h1>Projets du stage « {{ $internship->title }} »</h1>

    @can('manageInternships', $workspace)
        <p>
            <a class="button" href="{{ route('workspace.internships.projects.create', [$workspace, $internship]) }}">Nouveau projet</a>
        </p>
    @endcan

    @if ($projects->isEmpty())
        <p class="muted">Aucun projet pour ce stage.</p>
    @else
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Statut</th>
                        <th>Période</th>
                        <th>Activités</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($projects as $project)
                        <tr>
                            <td>{{ $project->name }}</td>
                            <td><span class="badge">{{ $project->statusLabel() }}</span></td>
                            <td>
                                @if ($project->start_date || $project->end_date)
                                    {{ $project->start_date?->format('d/m/Y') ?? '—' }} → {{ $project->end_date?->format('d/m/Y') ?? '—' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $project->activities_count }}</td>
                            <td>
                                <a class="button secondary small" href="{{ route('workspace.internships.projects.show', [$workspace, $internship, $project]) }}">Consulter</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection