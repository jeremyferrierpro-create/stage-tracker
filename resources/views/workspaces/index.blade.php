@extends('layouts.app')

@section('title', 'Mes espaces')

@section('content')
    <h1>Mes espaces de travail</h1>

    @forelse ($workspaces as $workspace)
        <div class="card">
            <h2>{{ $workspace->name }}</h2>
            <p>
                <span class="badge">{{ $workspace->typeLabel() }}</span>
                <span class="badge">{{ \App\Domain\Identity\Models\Workspace::roleLabel($workspace->pivot->role) }}</span>
            </p>
            <a class="button" href="{{ route('workspace.dashboard', $workspace) }}">
                Ouvrir l'espace
            </a>
        </div>
    @empty
        <div class="card">
            <p>Aucun espace de travail actif.</p>
            <p class="muted">
                Un administrateur peut en créer un avec
                <code>php artisan app:create-workspace</code>.
            </p>
        </div>
    @endforelse
@endsection
