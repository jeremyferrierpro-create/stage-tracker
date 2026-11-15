@extends('layouts.app')

@section('title', 'Nouveau dépôt — '.$project->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => $project->name, 'url' => route('workspace.internships.projects.show', [$workspace, $internship, $project])],
        ['label' => 'Nouveau dépôt'],
    ]"/>
@endsection

@section('content')
    <div class="card">
        <h1>Associer un dépôt Git</h1>
        <p class="muted">
            Le jeton (optionnel, lecture seule recommandée) est chiffré côté
            serveur et n'est jamais affiché. Sans jeton, GitHub limite l'API à
            60 requêtes par heure.
        </p>

        <form method="POST" action="{{ route('workspace.internships.projects.repositories.store', [$workspace, $internship, $project]) }}">
            @csrf

            <label for="provider">Fournisseur</label>
            <select id="provider" name="provider">
                <option value="github" @selected(old('provider', 'github') === 'github')>GitHub</option>
                <option value="gitlab" @selected(old('provider') === 'gitlab')>GitLab</option>
                <option value="other" @selected(old('provider') === 'other')>Autre</option>
            </select>
            @error('provider') <p class="error">{{ $message }}</p> @enderror

            <label for="external_id">Identifiant externe (ex. propriétaire/dépôt)</label>
            <input id="external_id" type="text" name="external_id" required maxlength="120" value="{{ old('external_id') }}" placeholder="mon-compte/mon-depot">
            @error('external_id') <p class="error">{{ $message }}</p> @enderror

            <label for="name">Nom affiché</label>
            <input id="name" type="text" name="name" required maxlength="160" value="{{ old('name') }}">
            @error('name') <p class="error">{{ $message }}</p> @enderror

            <label for="repository_url">URL du dépôt</label>
            <input id="repository_url" type="url" name="repository_url" required maxlength="500" value="{{ old('repository_url') }}" placeholder="https://github.com/mon-compte/mon-depot">
            @error('repository_url') <p class="error">{{ $message }}</p> @enderror

            <label for="default_branch">Branche par défaut</label>
            <input id="default_branch" type="text" name="default_branch" maxlength="120" value="{{ old('default_branch', 'main') }}">
            @error('default_branch') <p class="error">{{ $message }}</p> @enderror

            <label for="token">Jeton d'accès (optionnel)</label>
            <input id="token" type="password" name="token" maxlength="200" autocomplete="off">
            @error('token') <p class="error">{{ $message }}</p> @enderror

            <div class="actions">
                <button type="submit">Associer le dépôt</button>
                <a class="button secondary" href="{{ route('workspace.internships.projects.show', [$workspace, $internship, $project]) }}">Annuler</a>
            </div>
        </form>
    </div>

    @if (session('status'))
        <p class="card">{{ session('status') }}</p>
    @endif

    @if ($errors->has('connection'))
        <p class="card error">{{ $errors->first('connection') }}</p>
    @endif

    <div class="card">
        <h2>Tester la connexion</h2>
        <form method="POST" action="{{ route('workspace.internships.projects.repositories.test_connection', [$workspace, $internship, $project]) }}">
            @csrf
            <label for="test_url">URL du dépôt</label>
            <input id="test_url" type="url" name="repository_url" required maxlength="500" value="{{ old('repository_url') }}">
            <label for="test_token">Jeton (optionnel)</label>
            <input id="test_token" type="password" name="token" maxlength="200" autocomplete="off">
            <div class="actions">
                <button type="submit" class="secondary">Vérifier l'accès</button>
            </div>
        </form>
    </div>
@endsection
