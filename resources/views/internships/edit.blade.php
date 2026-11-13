@extends('layouts.app')

@section('title', 'Modifier '.$internship->title)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => 'Modifier'],
    ]"/>
@endsection

@section('content')
    <div class="card">
        <h1>Modifier le stage</h1>

        <form method="POST" action="{{ route('workspace.internships.update', [$workspace, $internship]) }}">
            @csrf
            @method('PUT')

            <label for="title">Titre</label>
            <input id="title" type="text" name="title" required maxlength="160" value="{{ old('title', $internship->title) }}">
            @error('title') <p class="error">{{ $message }}</p> @enderror

            <label for="host_organization_id">Organisation d'accueil</label>
            <select id="host_organization_id" name="host_organization_id" required>
                @foreach ($organizations as $organization)
                    <option value="{{ $organization->id }}" @selected(old('host_organization_id', $internship->host_organization_id) === $organization->id)>
                        {{ $organization->name }}
                    </option>
                @endforeach
            </select>
            @error('host_organization_id') <p class="error">{{ $message }}</p> @enderror

            <label for="training_program_id">Programme de formation</label>
            <select id="training_program_id" name="training_program_id">
                <option value="">— aucun —</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected(old('training_program_id', $internship->training_program_id) === $program->id)>
                        {{ $program->name }}
                    </option>
                @endforeach
            </select>
            @error('training_program_id') <p class="error">{{ $message }}</p> @enderror

            <label for="framework_version_id">Version de référentiel</label>
            <select id="framework_version_id" name="framework_version_id" required>
                @foreach ($versions as $version)
                    <option value="{{ $version->id }}" @selected(old('framework_version_id', $internship->framework_version_id) === $version->id)>
                        {{ $version->framework->name }} — {{ $version->version }}
                    </option>
                @endforeach
            </select>
            @error('framework_version_id') <p class="error">{{ $message }}</p> @enderror

            <label for="start_date">Date de début</label>
            <input id="start_date" type="date" name="start_date" required value="{{ old('start_date', $internship->start_date->format('Y-m-d')) }}">
            @error('start_date') <p class="error">{{ $message }}</p> @enderror

            <label for="end_date">Date de fin</label>
            <input id="end_date" type="date" name="end_date" required value="{{ old('end_date', $internship->end_date->format('Y-m-d')) }}">
            @error('end_date') <p class="error">{{ $message }}</p> @enderror

            <label for="objectives">Objectifs</label>
            <textarea id="objectives" name="objectives">{{ old('objectives', $internship->objectives) }}</textarea>
            @error('objectives') <p class="error">{{ $message }}</p> @enderror

            <label for="status">Statut</label>
            <select id="status" name="status" required>
                @foreach (\App\Domain\Internship\Models\Internship::STATUSES as $status)
                    <option value="{{ $status }}" @selected(old('status', $internship->status) === $status)>
                        {{ \App\Domain\Internship\Models\Internship::STATUS_LABELS[$status] }}
                    </option>
                @endforeach
            </select>
            @error('status') <p class="error">{{ $message }}</p> @enderror

            <div class="actions">
                <button type="submit">Enregistrer</button>
            </div>
        </form>
    </div>
@endsection