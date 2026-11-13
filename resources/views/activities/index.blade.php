@extends('layouts.app')

@section('title', 'Activités — '.$project->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => 'Projets', 'url' => route('workspace.internships.projects.index', [$workspace, $internship])],
        ['label' => $project->name, 'url' => route('workspace.internships.projects.show', [$workspace, $internship, $project])],
        ['label' => 'Activités'],
    ]"/>
@endsection

@section('content')
    <h1>Activités du projet « {{ $project->name }} »</h1>

    <p>
        <a class="button" href="{{ route('workspace.internships.projects.activities.create', [$workspace, $internship, $project]) }}">Nouvelle activité</a>
    </p>

    @if ($activities->isEmpty())
        <p class="muted">Aucune activité pour ce projet.</p>
    @else
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Type</th>
                        <th>Statut</th>
                        <th>Priorité</th>
                        <th>Période</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($activities as $activity)
                        <tr>
                            <td>
                                {{ $activity->title }}
                                @if ($activity->archived_at)
                                    <span class="muted">(archivée)</span>
                                @endif
                            </td>
                            <td>{{ $activity->typeLabel() }}</td>
                            <td><span class="badge">{{ $activity->statusLabel() }}</span></td>
                            <td>{{ $activity->priorityLabel() }}</td>
                            <td>
                                @if ($activity->start_date || $activity->end_date)
                                    {{ $activity->start_date?->format('d/m/Y') ?? '—' }} → {{ $activity->end_date?->format('d/m/Y') ?? '—' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <a class="button secondary small" href="{{ route('workspace.internships.projects.activities.show', [$workspace, $internship, $project, $activity]) }}">Consulter</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection