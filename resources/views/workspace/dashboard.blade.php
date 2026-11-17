@extends('layouts.app')

@section('title', $workspace->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name],
    ]"/>
@endsection

@section('content')
    <h1>{{ $workspace->name }}</h1>
    <p>
        <span class="badge">{{ $workspace->typeLabel() }}</span>
        @if ($membership)
            <span class="badge">{{ \App\Domain\Identity\Models\Workspace::roleLabel($membership->role) }}</span>
        @endif
    </p>

    <p class="muted">
        Vous êtes membre actif de cet espace. Toutes les données affichées ici
        sont filtrées par le serveur sur ce workspace (isolation multi-tenant).
    </p>

    <div class="grid">
        <div class="card">
            <h2>Stages</h2>
            <p><strong>{{ $internships->count() }}</strong> stage(s) au total —
                {{ $internships->where('status', 'active')->count() }} en cours,
                {{ $internships->where('status', 'completed')->count() }} terminé(s).
            </p>
            @if ($internships->isNotEmpty())
                <ul>
                    @foreach ($internships->take(5) as $internship)
                        <li>
                            <a href="{{ route('workspace.internships.show', [$workspace, $internship]) }}">
                                {{ $internship->title }}
                            </a>
                            <span class="muted">— {{ $internship->trainee->name }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <p>
                <a class="button secondary small" href="{{ route('workspace.internships.index', $workspace) }}">Voir les stages</a>
            </p>
        </div>

        <div class="card">
            <h2>Activités</h2>
            <p><strong>{{ $totalActivities }}</strong> activité(s) au total.</p>
            <ul>
                @foreach (\App\Domain\Project\Models\Activity::STATUSES as $status)
                    <li>
                        {{ \App\Domain\Project\Models\Activity::STATUS_LABELS[$status] }} :
                        <strong>{{ $activityCounts[$status] ?? 0 }}</strong>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="card">
        <h2>Journées récentes</h2>
        @if ($recentLogs->isEmpty())
            <p class="muted">Aucune journée saisie pour le moment.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Stage</th>
                        <th>Auteur</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentLogs as $log)
                        <tr>
                            <td>
                                <a href="{{ route('workspace.internships.daily_logs.show', [$workspace, $log->internship, $log]) }}">
                                    {{ $log->work_date->format('d/m/Y') }}
                                </a>
                            </td>
                            <td>{{ $log->internship->title }}</td>
                            <td>{{ $log->author->name }}</td>
                            <td><span class="badge">{{ $log->statusLabel() }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2>Raccourcis</h2>
        <div class="actions">
            <a class="button" href="{{ route('workspace.internships.index', $workspace) }}">Stages</a>
            @can('manageMembers', $workspace)
                <a class="button secondary" href="{{ route('workspace.competencies.index', $workspace) }}">Compétences</a>
                <a class="button secondary" href="{{ route('workspace.members.index', $workspace) }}">Membres et invitations</a>
            @endcan
            @can('viewAudit', $workspace)
                <a class="button secondary" href="{{ route('workspace.audit.index', $workspace) }}">Journal d'audit</a>
            @endcan
        </div>
    </div>
@endsection