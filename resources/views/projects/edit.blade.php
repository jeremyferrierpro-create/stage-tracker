@extends('layouts.app')

@section('title', 'Modifier '.$project->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => 'Projets', 'url' => route('workspace.internships.projects.index', [$workspace, $internship])],
        ['label' => $project->name, 'url' => route('workspace.internships.projects.show', [$workspace, $internship, $project])],
        ['label' => 'Modifier'],
    ]"/>
@endsection

@section('content')
    <div class="card">
        <h1>Modifier le projet</h1>

        <form method="POST" action="{{ route('workspace.internships.projects.update', [$workspace, $internship, $project]) }}">
            @csrf
            @method('PUT')

            <label for="name">Nom</label>
            <input id="name" type="text" name="name" required maxlength="160" value="{{ old('name', $project->name) }}">
            @error('name') <p class="error">{{ $message }}</p> @enderror

            <label for="description">Description</label>
            <textarea id="description" name="description">{{ old('description', $project->description) }}</textarea>
            @error('description') <p class="error">{{ $message }}</p> @enderror

            <label for="status">Statut</label>
            <select id="status" name="status" required>
                @foreach (\App\Domain\Project\Models\Project::STATUSES as $status)
                    <option value="{{ $status }}" @selected(old('status', $project->status) === $status)>
                        {{ \App\Domain\Project\Models\Project::STATUS_LABELS[$status] }}
                    </option>
                @endforeach
            </select>
            @error('status') <p class="error">{{ $message }}</p> @enderror

            <label for="start_date">Date de début</label>
            <input id="start_date" type="date" name="start_date" value="{{ old('start_date', optional($project->start_date)->format('Y-m-d')) }}">
            @error('start_date') <p class="error">{{ $message }}</p> @enderror

            <label for="end_date">Date de fin</label>
            <input id="end_date" type="date" name="end_date" value="{{ old('end_date', optional($project->end_date)->format('Y-m-d')) }}">
            @error('end_date') <p class="error">{{ $message }}</p> @enderror

            <div class="actions">
                <button type="submit">Enregistrer</button>
            </div>
        </form>
    </div>
@endsection