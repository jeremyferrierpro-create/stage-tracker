@extends('layouts.app')

@section('title', 'Journal d\'audit — '.$workspace->name)

@section('content')
    <p><a href="{{ route('workspace.dashboard', $workspace) }}">← Tableau de bord</a></p>

    <h1>Journal d'audit de {{ $workspace->name }}</h1>

    <p class="muted">
        Journal append-only : les entrées ne peuvent être ni modifiées ni supprimées
        (protection applicative et trigger PostgreSQL). Les métadonnées sont filtrées
        par liste blanche.
    </p>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Action</th>
                    <th>Acteur</th>
                    <th>Cible</th>
                    <th>Détails</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td><code>{{ $log->action }}</code></td>
                        <td>{{ $log->actor?->name ?? 'Système' }}</td>
                        <td>{{ $log->subjectLabel() }}</td>
                        <td>
                            @if ($log->metadata !== [])
                                <pre>{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            @else
                                <span class="muted">—</span>
                            @endif
                            <span class="muted">{{ $log->ip }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">Aucune entrée d'audit.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{ $logs->links() }}
    </div>
@endsection
