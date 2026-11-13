@extends('layouts.app')

@section('title', $activity->title)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => 'Projets', 'url' => route('workspace.internships.projects.index', [$workspace, $internship])],
        ['label' => $project->name, 'url' => route('workspace.internships.projects.show', [$workspace, $internship, $project])],
        ['label' => 'Activités', 'url' => route('workspace.internships.projects.activities.index', [$workspace, $internship, $project])],
        ['label' => $activity->title],
    ]"/>
@endsection

@section('content')
    <h1>{{ $activity->title }}</h1>
    <p>
        <span class="badge">{{ $activity->typeLabel() }}</span>
        <span class="badge">{{ $activity->statusLabel() }}</span>
        <span class="badge">{{ $activity->priorityLabel() }}</span>
        @if ($activity->archived_at)
            <span class="muted">(archivée)</span>
        @endif
    </p>

    <div class="card">
        @if ($activity->description)
            <p><strong>Description :</strong> {{ $activity->description }}</p>
        @endif
        @if ($activity->start_date || $activity->end_date)
            <p><strong>Période :</strong> {{ $activity->start_date?->format('d/m/Y') ?? '—' }} → {{ $activity->end_date?->format('d/m/Y') ?? '—' }}</p>
        @endif
        @foreach (['objectives' => 'Objectifs', 'results' => 'Résultats', 'difficulties' => 'Difficultés', 'solutions' => 'Solutions'] as $field => $label)
            @if ($activity->{$field})
                <p><strong>{{ $label }} :</strong> {{ $activity->{$field} }}</p>
            @endif
        @endforeach
    </div>

    <div class="actions">
        <a class="button secondary" href="{{ route('workspace.internships.projects.activities.edit', [$workspace, $internship, $project, $activity]) }}">Modifier</a>
        <form method="POST" action="{{ route('workspace.internships.projects.activities.archive', [$workspace, $internship, $project, $activity]) }}" class="inline">
            @csrf
            <button type="submit" class="danger small">Archiver</button>
        </form>
    </div>
@endsection