@extends('layouts.app')

@section('title', 'Nouveau stage — '.$workspace->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => 'Nouveau stage'],
    ]"/>
@endsection

@section('content')
    <div class="card">
        <h1>Nouveau stage</h1>

        <form method="POST" action="{{ route('workspace.internships.store', $workspace) }}">
            @csrf

            <label for="title">Titre du stage</label>
            <input id="title" type="text" name="title" required maxlength="160" value="{{ old('title') }}">
            @error('title') <p class="error">{{ $message }}</p> @enderror

            <label for="trainee_id">Stagiaire</label>
            <select id="trainee_id" name="trainee_id" required>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(old('trainee_id') === $user->id)>
                        {{ $user->name }} ({{ $user->email }})
                    </option>
                @endforeach
            </select>
            @error('trainee_id') <p class="error">{{ $message }}</p> @enderror

            <label for="company_name">Entreprise d'accueil</label>
            <input id="company_name" type="text" name="company_name" required maxlength="200" value="{{ old('company_name') }}">
            @error('company_name') <p class="error">{{ $message }}</p> @enderror

            <label for="start_date">Date de début</label>
            <input id="start_date" type="date" name="start_date" required value="{{ old('start_date') }}">
            @error('start_date') <p class="error">{{ $message }}</p> @enderror

            <label for="end_date">Date de fin</label>
            <input id="end_date" type="date" name="end_date" required value="{{ old('end_date') }}">
            @error('end_date') <p class="error">{{ $message }}</p> @enderror

            <label for="objectives">Objectifs du stage</label>
            <textarea id="objectives" name="objectives">{{ old('objectives') }}</textarea>
            @error('objectives') <p class="error">{{ $message }}</p> @enderror

            <label for="status">Statut</label>
            <select id="status" name="status" required>
                @foreach (\App\Domain\Internship\Models\Internship::STATUSES as $status)
                    <option value="{{ $status }}" @selected(old('status', 'draft') === $status)>
                        {{ \App\Domain\Internship\Models\Internship::STATUS_LABELS[$status] }}
                    </option>
                @endforeach
            </select>
            @error('status') <p class="error">{{ $message }}</p> @enderror

            <div class="actions">
                <button type="submit">Créer le stage</button>
                <a class="button secondary" href="{{ route('workspace.internships.index', $workspace) }}">Annuler</a>
            </div>
        </form>
    </div>
@endsection