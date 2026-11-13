@extends('layouts.app')

@section('title', 'Nouveau référentiel')

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => 'Référentiels', 'url' => route('frameworks.index')],
        ['label' => 'Nouveau référentiel'],
    ]"/>
@endsection

@section('content')
    <div class="card">
        <h1>Nouveau référentiel</h1>

        <form method="POST" action="{{ route('frameworks.store') }}">
            @csrf

            <label for="name">Nom</label>
            <input id="name" type="text" name="name" required maxlength="160" value="{{ old('name') }}">
            @error('name') <p class="error">{{ $message }}</p> @enderror

            <label for="issuing_authority">Organisme émetteur</label>
            <input id="issuing_authority" type="text" name="issuing_authority" maxlength="160" value="{{ old('issuing_authority') }}">
            @error('issuing_authority') <p class="error">{{ $message }}</p> @enderror

            <label for="description">Description</label>
            <textarea id="description" name="description" maxlength="2000">{{ old('description') }}</textarea>
            @error('description') <p class="error">{{ $message }}</p> @enderror

            <div class="actions">
                <button type="submit">Créer le référentiel</button>
                <a class="button secondary" href="{{ route('frameworks.index') }}">Annuler</a>
            </div>
        </form>
    </div>
@endsection