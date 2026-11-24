@extends('layouts.app')

@section('title', 'Modifier '.$activity->title)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => 'Projets', 'url' => route('workspace.internships.projects.index', [$workspace, $internship])],
        ['label' => $project->name, 'url' => route('workspace.internships.projects.show', [$workspace, $internship, $project])],
        ['label' => 'Activités', 'url' => route('workspace.internships.projects.activities.index', [$workspace, $internship, $project])],
        ['label' => $activity->title, 'url' => route('workspace.internships.projects.activities.show', [$workspace, $internship, $project, $activity])],
        ['label' => 'Modifier'],
    ]"/>
@endsection

@section('content')
    <div class="card">
        <h1>Modifier l'activité</h1>

        <form method="POST" action="{{ route('workspace.internships.projects.activities.update', [$workspace, $internship, $project, $activity]) }}">
            @csrf
            @method('PUT')

            <label for="title">Titre</label>
            <input id="title" type="text" name="title" required maxlength="200" value="{{ old('title', $activity->title) }}">
            @error('title') <p class="error">{{ $message }}</p> @enderror

            <label for="description">Description</label>
            <textarea id="description" name="description">{{ old('description', $activity->description) }}</textarea>
            @error('description') <p class="error">{{ $message }}</p> @enderror

            <label for="type">Type</label>
            <select id="type" name="type" required>
                @foreach (\App\Domain\Project\Models\Activity::TYPES as $type)
                    <option value="{{ $type }}" @selected(old('type', $activity->type) === $type)>
                        {{ \App\Domain\Project\Models\Activity::TYPE_LABELS[$type] }}
                    </option>
                @endforeach
            </select>
            @error('type') <p class="error">{{ $message }}</p> @enderror

            <label for="status">Statut</label>
            <select id="status" name="status" required>
                @foreach (\App\Domain\Project\Models\Activity::STATUSES as $status)
                    <option value="{{ $status }}" @selected(old('status', $activity->status) === $status)>
                        {{ \App\Domain\Project\Models\Activity::STATUS_LABELS[$status] }}
                    </option>
                @endforeach
            </select>
            @error('status') <p class="error">{{ $message }}</p> @enderror

            <label for="priority">Priorité</label>
            <select id="priority" name="priority" required>
                @foreach (\App\Domain\Project\Models\Activity::PRIORITIES as $priority)
                    <option value="{{ $priority }}" @selected(old('priority', $activity->priority) === $priority)>
                        {{ \App\Domain\Project\Models\Activity::PRIORITY_LABELS[$priority] }}
                    </option>
                @endforeach
            </select>
            @error('priority') <p class="error">{{ $message }}</p> @enderror

            <label for="start_date">Date de début</label>
            <input id="start_date" type="date" name="start_date" value="{{ old('start_date', optional($activity->start_date)->format('Y-m-d')) }}">
            @error('start_date') <p class="error">{{ $message }}</p> @enderror

            <label for="end_date">Date de fin</label>
            <input id="end_date" type="date" name="end_date" value="{{ old('end_date', optional($activity->end_date)->format('Y-m-d')) }}">
            @error('end_date') <p class="error">{{ $message }}</p> @enderror

            <label for="objectives">Objectifs</label>
            <textarea id="objectives" name="objectives">{{ old('objectives', $activity->objectives) }}</textarea>

            <label for="results">Résultats</label>
            <textarea id="results" name="results">{{ old('results', $activity->results) }}</textarea>

            <label for="difficulties">Difficultés</label>
            <textarea id="difficulties" name="difficulties">{{ old('difficulties', $activity->difficulties) }}</textarea>

            <label for="solutions">Solutions</label>
            <textarea id="solutions" name="solutions">{{ old('solutions', $activity->solutions) }}</textarea>

            <label for="plan_entry_ids">Entrées de planning réalisées</label>
            <select id="plan_entry_ids" name="plan_entry_ids[]" multiple size="6">
                @foreach ($planEntries as $entry)
                    <option value="{{ $entry->id }}" @selected(in_array($entry->id, old('plan_entry_ids', $linkedPlanEntryIds)))>
                        {{ $entry->plan_date->format('d/m/Y') }} — {{ $entry->slotLabel() }} — {{ $entry->title }}
                    </option>
                @endforeach
            </select>
            <p class="muted">Laissez vide pour délier toutes les entrées de planning.</p>
            @error('plan_entry_ids') <p class="error">{{ $message }}</p> @enderror
            @error('plan_entry_ids.*') <p class="error">{{ $message }}</p> @enderror

            <div class="actions">
                <button type="submit">Enregistrer</button>
            </div>
        </form>
    </div>
@endsection