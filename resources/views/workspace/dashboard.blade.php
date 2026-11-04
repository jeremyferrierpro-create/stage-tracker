@extends('layouts.app')

@section('title', $workspace->name)

@section('content')
    <div class="card">
        <h1>{{ $workspace->name }}</h1>
        <p>
            <span class="badge">{{ $workspace->typeLabel() }}</span>
            <span class="badge">{{ \App\Domain\Identity\Models\Workspace::roleLabel($membership->role) }}</span>
        </p>

        <p>
            Vous êtes membre actif de cet espace. Toutes les données affichées ici
            sont filtrées par le serveur sur ce workspace (isolation multi-tenant).
        </p>

        <p class="muted">
            Les modules métier (organisations, stages, référentiels, journal,
            preuves, évaluations, rapports) arrivent dans les tâches suivantes.
        </p>

        <a class="button" href="{{ route('workspaces.index') }}">Changer d'espace</a>
    </div>
@endsection
