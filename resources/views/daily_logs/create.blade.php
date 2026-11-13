@extends('layouts.app')

@section('title', 'Nouvelle journée — '.$internship->title)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => 'Journal', 'url' => route('workspace.internships.daily_logs.index', [$workspace, $internship])],
        ['label' => 'Nouvelle journée'],
    ]"/>
@endsection

@section('content')
    <div class="card">
        <h1>Nouvelle journée de journal</h1>

        <form method="POST" action="{{ route('workspace.internships.daily_logs.store', [$workspace, $internship]) }}">
            @csrf

            <label for="work_date">Date</label>
            <input id="work_date" type="date" name="work_date" required value="{{ old('work_date', now()->format('Y-m-d')) }}">
            @error('work_date') <p class="error">{{ $message }}</p> @enderror

            <label for="start_time">Heure de début</label>
            <input id="start_time" type="time" name="start_time" value="{{ old('start_time') }}">
            @error('start_time') <p class="error">{{ $message }}</p> @enderror

            <label for="end_time">Heure de fin</label>
            <input id="end_time" type="time" name="end_time" value="{{ old('end_time') }}">
            @error('end_time') <p class="error">{{ $message }}</p> @enderror

            <label for="duration_minutes">Durée (minutes)</label>
            <input id="duration_minutes" type="number" name="duration_minutes" min="0" value="{{ old('duration_minutes') }}">
            @error('duration_minutes') <p class="error">{{ $message }}</p> @enderror

            <label for="objectives">Objectifs de la journée</label>
            <textarea id="objectives" name="objectives">{{ old('objectives') }}</textarea>

            <label for="work_done">Travaux réalisés</label>
            <textarea id="work_done" name="work_done">{{ old('work_done') }}</textarea>

            <label for="difficulties">Difficultés</label>
            <textarea id="difficulties" name="difficulties">{{ old('difficulties') }}</textarea>

            <label for="solutions">Solutions</label>
            <textarea id="solutions" name="solutions">{{ old('solutions') }}</textarea>

            <label for="results">Résultats</label>
            <textarea id="results" name="results">{{ old('results') }}</textarea>

            <label for="remaining">Reste à faire</label>
            <textarea id="remaining" name="remaining">{{ old('remaining') }}</textarea>

            <label for="private_notes">Notes privées (non partagées)</label>
            <textarea id="private_notes" name="private_notes">{{ old('private_notes') }}</textarea>

            <div class="actions">
                <button type="submit">Créer la journée</button>
                <a class="button secondary" href="{{ route('workspace.internships.daily_logs.index', [$workspace, $internship]) }}">Annuler</a>
            </div>
        </form>
    </div>
@endsection