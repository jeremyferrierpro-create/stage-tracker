@extends('layouts.app')

@section('title', 'Modifier '.$evidence->title)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Preuves', 'url' => route('workspace.evidences.index', $workspace)],
        ['label' => $evidence->title, 'url' => route('workspace.evidences.show', [$workspace, $evidence])],
        ['label' => 'Modifier'],
    ]"/>
@endsection

@section('content')
    <div class="card">
        <h1>Modifier la preuve</h1>

        <form method="POST" action="{{ route('workspace.evidences.update', [$workspace, $evidence]) }}">
            @csrf
            @method('PUT')

            <label for="title">Titre</label>
            <input id="title" type="text" name="title" required maxlength="200" value="{{ old('title', $evidence->title) }}">
            @error('title') <p class="error">{{ $message }}</p> @enderror

            <label for="type">Type</label>
            <select id="type" name="type" required>
                @foreach (\App\Domain\Evidence\Models\Evidence::TYPES as $type)
                    <option value="{{ $type }}" @selected(old('type', $evidence->type) === $type)>
                        {{ \App\Domain\Evidence\Models\Evidence::TYPE_LABELS[$type] }}
                    </option>
                @endforeach
            </select>
            @error('type') <p class="error">{{ $message }}</p> @enderror

            <label for="description">Description</label>
            <textarea id="description" name="description">{{ old('description', $evidence->description) }}</textarea>
            @error('description') <p class="error">{{ $message }}</p> @enderror

            <label for="demonstrated">Ce que la preuve démontre</label>
            <textarea id="demonstrated" name="demonstrated" required>{{ old('demonstrated', $evidence->demonstrated) }}</textarea>
            @error('demonstrated') <p class="error">{{ $message }}</p> @enderror

            <label for="visibility">Visibilité</label>
            <select id="visibility" name="visibility" required>
                @foreach (\App\Domain\Evidence\Models\Evidence::VISIBILITIES as $visibility)
                    <option value="{{ $visibility }}" @selected(old('visibility', $evidence->visibility) === $visibility)>
                        {{ \App\Domain\Evidence\Models\Evidence::VISIBILITY_LABELS[$visibility] }}
                    </option>
                @endforeach
            </select>
            @error('visibility') <p class="error">{{ $message }}</p> @enderror

            <label for="external_url">Lien externe</label>
            <input id="external_url" type="url" name="external_url" maxlength="500" value="{{ old('external_url', $evidence->external_url) }}">
            @error('external_url') <p class="error">{{ $message }}</p> @enderror

            <label for="captured_at">Date de capture</label>
            <input id="captured_at" type="date" name="captured_at" value="{{ old('captured_at', optional($evidence->captured_at)->format('Y-m-d')) }}">
            @error('captured_at') <p class="error">{{ $message }}</p> @enderror

            <div class="actions">
                <button type="submit">Enregistrer</button>
            </div>
        </form>
    </div>
@endsection