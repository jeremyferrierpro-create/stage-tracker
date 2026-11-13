@extends('layouts.app')

@section('title', 'Nouveau projet — '.$internship->title)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => 'Projets', 'url' => route('workspace.internships.projects.index', [$workspace, $internship])],
        ['label' => 'Nouveau projet'],
    ]"/>
@endsection

@section('content')
    <div class="card">
        <h1>Nouveau projet</h1>

        <form method="POST" action="{{ route('workspace.internships.projects.store', [$workspace, $internship]) }}">
            @csrf

            <label for="name">Nom du projet</label>
            <input id="name" type="text" name="name" required maxlength="160" value="{{ old('name') }}">
            @error('name') <p class="error">{{ $message }}</p> @enderror

            <label for="description">Description</label>
            <textarea id="description" name="description">{{ old('description') }}</textarea>
            @error('description') <p class="error">{{ $message }}</p> @enderror

            <label for="status">Statut</label>
            <select id="status" name="status" required>
                @foreach (\App\Domain\Project\Models\Project::STATUSES as $status)
                    <option value="{{ $status }}" @selected(old('status', 'active') === $status)>
                        {{ \App\Domain\Project\Models\Project::STATUS_LABELS[$status] }}
                    </option>
                @endforeach
            </select>
            @error('status') <p class="error">{{ $message }}</p> @enderror

            <label for="start_date">Date de début</label>
            <input id="start_date" type="date" name="start_date" value="{{ old('start_date') }}">
            @error('start_date') <p class="error">{{ $message }}</p> @enderror

            <label for="end_date">Date de fin</label>
            <input id="end_date" type="date" name="end_date" value="{{ old('end_date') }}">
            @error('end_date') <p class="error">{{ $message }}</p> @enderror

            <div class="actions">
                <button type="submit">Créer le projet</button>
                <a class="button secondary" href="{{ route('workspace.internships.projects.index', [$workspace, $internship]) }}">Annuler</a>
            </div>
        </form>
    </div>
@endsection