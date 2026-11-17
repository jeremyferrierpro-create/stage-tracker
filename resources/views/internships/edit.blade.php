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

            <label for="company_name">Entreprise d'accueil</label>
            <input id="company_name" type="text" name="company_name" required maxlength="200" value="{{ old('company_name', $internship->company_name) }}">
            @error('company_name') <p class="error">{{ $message }}</p> @enderror

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