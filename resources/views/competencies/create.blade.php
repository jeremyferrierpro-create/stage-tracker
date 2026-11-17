@extends('layouts.app')

@section('title', 'Nouvelle compétence')

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Compétences', 'url' => route('workspace.competencies.index', $workspace)],
        ['label' => 'Nouvelle'],
    ]"/>
@endsection

@section('content')
    <h1>Nouvelle compétence</h1>

    <form method="POST" action="{{ route('workspace.competencies.store', $workspace) }}">
        @csrf
        <label for="code">Code (ex. C1.1)</label>
        <input id="code" type="text" name="code" required maxlength="40" value="{{ old('code') }}">

        <label for="title">Intitulé</label>
        <input id="title" type="text" name="title" required maxlength="200" value="{{ old('title') }}">

        <label for="description">Description (optionnelle)</label>
        <textarea id="description" name="description" rows="2" maxlength="5000">{{ old('description') }}</textarea>

        <div class="actions">
            <button type="submit">Créer</button>
            <a class="button secondary" href="{{ route('workspace.competencies.index', $workspace) }}">Annuler</a>
        </div>
    </form>
@endsection
