@extends('layouts.app')

@section('title', 'Nouvelle preuve — '.$workspace->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Preuves', 'url' => route('workspace.evidences.index', $workspace)],
        ['label' => 'Nouvelle preuve'],
    ]"/>
@endsection

@section('content')
    <div class="card">
        <h1>Nouvelle preuve</h1>

        <form method="POST" action="{{ route('workspace.evidences.store', $workspace) }}" enctype="multipart/form-data">
            @csrf

            <label for="title">Titre</label>
            <input id="title" type="text" name="title" required maxlength="200" value="{{ old('title') }}">
            @error('title') <p class="error">{{ $message }}</p> @enderror

            <label for="type">Type</label>
            <select id="type" name="type" required>
                @foreach (\App\Domain\Evidence\Models\Evidence::TYPES as $type)
                    <option value="{{ $type }}" @selected(old('type', 'screenshot') === $type)>
                        {{ \App\Domain\Evidence\Models\Evidence::TYPE_LABELS[$type] }}
                    </option>
                @endforeach
            </select>
            @error('type') <p class="error">{{ $message }}</p> @enderror

            <label for="description">Description</label>
            <textarea id="description" name="description">{{ old('description') }}</textarea>
            @error('description') <p class="error">{{ $message }}</p> @enderror

            <label for="demonstrated">Ce que la preuve démontre</label>
            <textarea id="demonstrated" name="demonstrated" required>{{ old('demonstrated') }}</textarea>
            @error('demonstrated') <p class="error">{{ $message }}</p> @enderror

            <label for="visibility">Visibilité</label>
            <select id="visibility" name="visibility" required>
                @foreach (\App\Domain\Evidence\Models\Evidence::VISIBILITIES as $visibility)
                    <option value="{{ $visibility }}" @selected(old('visibility', 'private') === $visibility)>
                        {{ \App\Domain\Evidence\Models\Evidence::VISIBILITY_LABELS[$visibility] }}
                    </option>
                @endforeach
            </select>
            @error('visibility') <p class="error">{{ $message }}</p> @enderror

            <label for="file">Fichier (optionnel)</label>
            <input id="file" type="file" name="file">
            @error('file') <p class="error">{{ $message }}</p> @enderror

            <label for="external_url">Lien externe (optionnel)</label>
            <input id="external_url" type="url" name="external_url" maxlength="500" value="{{ old('external_url') }}">
            @error('external_url') <p class="error">{{ $message }}</p> @enderror

            <label for="captured_at">Date de capture</label>
            <input id="captured_at" type="date" name="captured_at" value="{{ old('captured_at') }}">
            @error('captured_at') <p class="error">{{ $message }}</p> @enderror

            <div class="actions">
                <button type="submit">Créer la preuve</button>
                <a class="button secondary" href="{{ route('workspace.evidences.index', $workspace) }}">Annuler</a>
            </div>
        </form>
    </div>
@endsection