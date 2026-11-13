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

    <div class="card">
        <h2>Tâches (Kanban)</h2>

        <form method="POST" action="{{ route('workspace.tasks.store', [$workspace, $internship, $project, $activity]) }}" class="actions" style="margin-bottom: 1rem;">
            @csrf
            <div>
                <label>Titre</label>
                <input type="text" name="title" required maxlength="200" placeholder="Nouvelle tâche">
            </div>
            <div>
                <label>Priorité</label>
                <select name="priority">
                    @foreach (\App\Domain\Project\Models\Task::PRIORITIES as $priority)
                        <option value="{{ $priority }}">{{ \App\Domain\Project\Models\Task::PRIORITY_LABELS[$priority] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Statut</label>
                <select name="status">
                    @foreach (\App\Domain\Project\Models\Task::STATUSES as $status)
                        <option value="{{ $status }}">{{ \App\Domain\Project\Models\Task::STATUS_LABELS[$status] }}</option>
                    @endforeach
                </select>
            </div>
            <div style="align-self: end;">
                <button type="submit" class="small">Ajouter</button>
            </div>
        </form>

        @if ($tasks->isEmpty())
            <p class="muted">Aucune tâche.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Statut</th>
                        <th>Priorité</th>
                        <th>Échéance</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tasks as $task)
                        <tr>
                            <td>{{ $task->title }}</td>
                            <td>
                                <form method="POST" action="{{ route('workspace.tasks.update', [$workspace, $internship, $project, $activity, $task]) }}" class="inline">
                                    @csrf
                                    @method('PUT')
                                    <select name="status" onchange="this.form.submit()">
                                        @foreach (\App\Domain\Project\Models\Task::STATUSES as $status)
                                            <option value="{{ $status }}" @selected($task->status === $status)>
                                                {{ \App\Domain\Project\Models\Task::STATUS_LABELS[$status] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                            <td>{{ $task->priorityLabel() }}</td>
                            <td>{{ $task->due_date?->format('d/m/Y') ?? '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('workspace.tasks.destroy', [$workspace, $internship, $project, $activity, $task]) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="danger small">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="actions">
        <a class="button secondary" href="{{ route('workspace.internships.projects.activities.edit', [$workspace, $internship, $project, $activity]) }}">Modifier</a>
        <form method="POST" action="{{ route('workspace.internships.projects.activities.archive', [$workspace, $internship, $project, $activity]) }}" class="inline">
            @csrf
            <button type="submit" class="danger small">Archiver</button>
        </form>
    </div>
@endsection