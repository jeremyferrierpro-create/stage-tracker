@extends('layouts.app')

@section('title', 'Nouvelle activité — '.$project->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => 'Projets', 'url' => route('workspace.internships.projects.index', [$workspace, $internship])],
        ['label' => $project->name, 'url' => route('workspace.internships.projects.show', [$workspace, $internship, $project])],
        ['label' => 'Activités', 'url' => route('workspace.internships.projects.activities.index', [$workspace, $internship, $project])],
        ['label' => 'Nouvelle activité'],
    ]"/>
@endsection

@section('content')
    <div class="card">
        <h1>Nouvelle activité</h1>

        <form method="POST" action="{{ route('workspace.internships.projects.activities.store', [$workspace, $internship, $project]) }}">
            @csrf

            <label for="title">Titre</label>
            <input id="title" type="text" name="title" required maxlength="200" value="{{ old('title') }}">
            @error('title') <p class="error">{{ $message }}</p> @enderror

            <label for="description">Description</label>
            <textarea id="description" name="description">{{ old('description') }}</textarea>
            @error('description') <p class="error">{{ $message }}</p> @enderror

            <label for="type">Type</label>
            <select id="type" name="type" required>
                @foreach (\App\Domain\Project\Models\Activity::TYPES as $type)
                    <option value="{{ $type }}" @selected(old('type', 'feature') === $type)>
                        {{ \App\Domain\Project\Models\Activity::TYPE_LABELS[$type] }}
                    </option>
                @endforeach
            </select>
            @error('type') <p class="error">{{ $message }}</p> @enderror

            <label for="status">Statut (Kanban)</label>
            <select id="status" name="status" required>
                @foreach (\App\Domain\Project\Models\Activity::STATUSES as $status)
                    <option value="{{ $status }}" @selected(old('status', 'todo') === $status)>
                        {{ \App\Domain\Project\Models\Activity::STATUS_LABELS[$status] }}
                    </option>
                @endforeach
            </select>
            @error('status') <p class="error">{{ $message }}</p> @enderror

            <label for="priority">Priorité</label>
            <select id="priority" name="priority" required>
                @foreach (\App\Domain\Project\Models\Activity::PRIORITIES as $priority)
                    <option value="{{ $priority }}" @selected(old('priority', 'medium') === $priority)>
                        {{ \App\Domain\Project\Models\Activity::PRIORITY_LABELS[$priority] }}
                    </option>
                @endforeach
            </select>
            @error('priority') <p class="error">{{ $message }}</p> @enderror

            <label for="start_date">Date de début</label>
            <input id="start_date" type="date" name="start_date" value="{{ old('start_date') }}">
            @error('start_date') <p class="error">{{ $message }}</p> @enderror

            <label for="end_date">Date de fin</label>
            <input id="end_date" type="date" name="end_date" value="{{ old('end_date') }}">
            @error('end_date') <p class="error">{{ $message }}</p> @enderror

            <label for="objectives">Objectifs</label>
            <textarea id="objectives" name="objectives">{{ old('objectives') }}</textarea>

            <label for="results">Résultats obtenus</label>
            <textarea id="results" name="results">{{ old('results') }}</textarea>

            <label for="difficulties">Difficultés</label>
            <textarea id="difficulties" name="difficulties">{{ old('difficulties') }}</textarea>

            <label for="solutions">Solutions apportées</label>
            <textarea id="solutions" name="solutions">{{ old('solutions') }}</textarea>

            <div class="actions">
                <button type="submit">Créer l'activité</button>
                <a class="button secondary" href="{{ route('workspace.internships.projects.activities.index', [$workspace, $internship, $project]) }}">Annuler</a>
            </div>
        </form>
    </div>
@endsection