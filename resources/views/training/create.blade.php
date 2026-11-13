@extends('layouts.app')

@section('title', 'Nouveau programme — '.$workspace->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Formations', 'url' => route('workspace.training.index', $workspace)],
        ['label' => 'Nouveau programme'],
    ]"/>
@endsection

@section('content')
    <div class="card">
        <h1>Nouveau programme de formation</h1>

        <form method="POST" action="{{ route('workspace.training.store', $workspace) }}">
            @csrf

            <label for="organization_id">Organisation (organisme de formation)</label>
            <select id="organization_id" name="organization_id" required>
                @foreach ($organizations as $organization)
                    <option value="{{ $organization->id }}">
                        {{ $organization->name }} ({{ \App\Domain\Organization\Models\Organization::TYPE_LABELS[$organization->type] ?? $organization->type }})
                    </option>
                @endforeach
            </select>
            @error('organization_id') <p class="error">{{ $message }}</p> @enderror

            <label for="name">Nom du programme</label>
            <input id="name" type="text" name="name" required maxlength="160" value="{{ old('name') }}">
            @error('name') <p class="error">{{ $message }}</p> @enderror

            <label for="certification">Certification visée</label>
            <input id="certification" type="text" name="certification" maxlength="160" value="{{ old('certification') }}">
            @error('certification') <p class="error">{{ $message }}</p> @enderror

            <label for="start_date">Date de début</label>
            <input id="start_date" type="date" name="start_date" value="{{ old('start_date') }}">
            @error('start_date') <p class="error">{{ $message }}</p> @enderror

            <label for="end_date">Date de fin</label>
            <input id="end_date" type="date" name="end_date" value="{{ old('end_date') }}">
            @error('end_date') <p class="error">{{ $message }}</p> @enderror

            <div class="actions">
                <button type="submit">Créer le programme</button>
                <a class="button secondary" href="{{ route('workspace.training.index', $workspace) }}">Annuler</a>
            </div>
        </form>
    </div>
@endsection