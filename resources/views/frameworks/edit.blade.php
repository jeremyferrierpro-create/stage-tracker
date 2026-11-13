@extends('layouts.app')

@section('title', 'Modifier '.$framework->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => 'Référentiels', 'url' => route('frameworks.index')],
        ['label' => $framework->name, 'url' => route('frameworks.show', $framework)],
        ['label' => 'Modifier'],
    ]"/>
@endsection

@section('content')
    <div class="card">
        <h1>Modifier le référentiel</h1>

        <form method="POST" action="{{ route('frameworks.update', $framework) }}">
            @csrf
            @method('PUT')

            <label for="name">Nom</label>
            <input id="name" type="text" name="name" required maxlength="160" value="{{ old('name', $framework->name) }}">
            @error('name') <p class="error">{{ $message }}</p> @enderror

            <label for="issuing_authority">Organisme émetteur</label>
            <input id="issuing_authority" type="text" name="issuing_authority" maxlength="160" value="{{ old('issuing_authority', $framework->issuing_authority) }}">
            @error('issuing_authority') <p class="error">{{ $message }}</p> @enderror

            <label for="description">Description</label>
            <textarea id="description" name="description" maxlength="2000">{{ old('description', $framework->description) }}</textarea>
            @error('description') <p class="error">{{ $message }}</p> @enderror

            <div class="actions">
                <button type="submit">Enregistrer</button>
            </div>
        </form>
    </div>
@endsection