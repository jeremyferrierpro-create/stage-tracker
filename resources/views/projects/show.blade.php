@extends('layouts.app')

@section('title', $project->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => 'Projets', 'url' => route('workspace.internships.projects.index', [$workspace, $internship])],
        ['label' => $project->name],
    ]"/>
@endsection

@section('content')
    <h1>{{ $project->name }}</h1>
    <p><span class="badge">{{ $project->statusLabel() }}</span></p>
    @if ($project->description)
        <p>{{ $project->description }}</p>
    @endif

    <div class="actions">
        <a class="button secondary" href="{{ route('workspace.internships.projects.activities.index', [$workspace, $internship, $project]) }}">Activités</a>
        <a class="button secondary" href="{{ route('workspace.internships.projects.repositories.create', [$workspace, $internship, $project]) }}">Dépôts Git</a>
        @can('manageInternships', $workspace)
            <a class="button secondary" href="{{ route('workspace.internships.projects.edit', [$workspace, $internship, $project]) }}">Modifier le projet</a>
        @endcan
    </div>

    @if ($project->repositories->isNotEmpty())
        <div class="card">
            <h2>Dépôts Git</h2>
            <ul>
                @foreach ($project->repositories as $repository)
                    <li>
                        <a href="{{ route('workspace.internships.projects.repositories.show', [$workspace, $internship, $project, $repository]) }}">{{ $repository->name }}</a>
                        <span class="badge">{{ $repository->provider }}</span>
                        <span class="muted">{{ $repository->repository_url }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <h2>Activités</h2>
        @if ($project->activities->isEmpty())
            <p class="muted">Aucune activité pour ce projet.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Type</th>
                        <th>Statut</th>
                        <th>Priorité</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($project->activities as $activity)
                        <tr>
                            <td>{{ $activity->title }}</td>
                            <td>{{ $activity->typeLabel() }}</td>
                            <td><span class="badge">{{ $activity->statusLabel() }}</span></td>
                            <td>{{ $activity->priorityLabel() }}</td>
                            <td>
                                <a class="button secondary small" href="{{ route('workspace.internships.projects.activities.show', [$workspace, $internship, $project, $activity]) }}">Consulter</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <p>
            <a class="button" href="{{ route('workspace.internships.projects.activities.create', [$workspace, $internship, $project]) }}">Nouvelle activité</a>
        </p>
    </div>
@endsection