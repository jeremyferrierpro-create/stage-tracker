@extends('layouts.app')

@section('title', 'Modifier la journée — '.$log->work_date->format('d/m/Y'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => 'Journal', 'url' => route('workspace.internships.daily_logs.index', [$workspace, $internship])],
        ['label' => $log->work_date->format('d/m/Y'), 'url' => route('workspace.internships.daily_logs.show', [$workspace, $internship, $log])],
        ['label' => 'Modifier'],
    ]"/>
@endsection

@section('content')
    <div class="card">
        <h1>Modifier la journée</h1>

        <form method="POST" action="{{ route('workspace.internships.daily_logs.update', [$workspace, $internship, $log]) }}">
            @csrf
            @method('PUT')

            <label for="work_date">Date</label>
            <input id="work_date" type="date" name="work_date" required value="{{ old('work_date', $log->work_date->format('Y-m-d')) }}">
            @error('work_date') <p class="error">{{ $message }}</p> @enderror

            <label for="start_time">Heure de début</label>
            <input id="start_time" type="time" name="start_time" value="{{ old('start_time', $log->start_time) }}">
            @error('start_time') <p class="error">{{ $message }}</p> @enderror

            <label for="end_time">Heure de fin</label>
            <input id="end_time" type="time" name="end_time" value="{{ old('end_time', $log->end_time) }}">
            @error('end_time') <p class="error">{{ $message }}</p> @enderror

            <label for="duration_minutes">Durée (minutes)</label>
            <input id="duration_minutes" type="number" name="duration_minutes" min="0" value="{{ old('duration_minutes', $log->duration_minutes) }}">
            @error('duration_minutes') <p class="error">{{ $message }}</p> @enderror

            <label for="objectives">Objectifs</label>
            <textarea id="objectives" name="objectives">{{ old('objectives', $log->objectives) }}</textarea>

            <label for="work_done">Travaux réalisés</label>
            <textarea id="work_done" name="work_done">{{ old('work_done', $log->work_done) }}</textarea>

            <label for="difficulties">Difficultés</label>
            <textarea id="difficulties" name="difficulties">{{ old('difficulties', $log->difficulties) }}</textarea>

            <label for="solutions">Solutions</label>
            <textarea id="solutions" name="solutions">{{ old('solutions', $log->solutions) }}</textarea>

            <label for="results">Résultats</label>
            <textarea id="results" name="results">{{ old('results', $log->results) }}</textarea>

            <label for="remaining">Reste à faire</label>
            <textarea id="remaining" name="remaining">{{ old('remaining', $log->remaining) }}</textarea>

            <label for="private_notes">Notes privées</label>
            <textarea id="private_notes" name="private_notes">{{ old('private_notes', $log->private_notes) }}</textarea>

            <div class="actions">
                <button type="submit">Enregistrer</button>
            </div>
        </form>
    </div>
@endsection