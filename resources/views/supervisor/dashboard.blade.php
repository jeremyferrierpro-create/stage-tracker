@extends('layouts.app')

@section('title', 'Mes suivis')

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Mes suivis'],
    ]"/>
@endsection

@section('content')
    <h1>Mes suivis (maître de stage / tuteur)</h1>
    <p class="muted">
        Vue limitée aux stages où vous êtes rattaché. Les éléments privés du
        stagiaire (preuves privées, notes personnelles du journal) ne sont
        jamais affichés ici.
    </p>

    @if ($panels->isEmpty())
        <p class="card">Aucun stage actif vous est rattaché pour le moment.</p>
    @endif

    @foreach ($panels as $panel)
        <div class="card">
            <h2>{{ $panel['internship']->title }}</h2>
            <p class="muted">
                Stagiaire : {{ $panel['internship']->trainee->name }} —
                votre rôle : {{ \App\Domain\Internship\Models\InternshipParticipant::ROLE_LABELS[$panel['role']] }}
            </p>

            @if ($panel['pendingReviews']->isNotEmpty())
                <h3>Revues à traiter</h3>
                <ul>
                    @foreach ($panel['pendingReviews'] as $entry)
                        <li>
                            <a href="{{ route('workspace.internships.projects.activities.show', [$workspace, $panel['internship'], $entry->activity->project, $entry->activity]) }}">
                                {{ $entry->activity->title }}
                            </a>
                            <span class="badge">{{ $entry->review->statusLabel() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <h3>Activités récentes</h3>
            @if ($panel['activities']->isEmpty())
                <p class="muted">Aucune activité.</p>
            @else
                <ul>
                    @foreach ($panel['activities'] as $activity)
                        <li>
                            <a href="{{ route('workspace.internships.projects.activities.show', [$workspace, $panel['internship'], $activity->project, $activity]) }}">
                                {{ $activity->title }}
                            </a>
                            <span class="badge">{{ $activity->statusLabel() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <h3>Preuves partagées (stage / partagées)</h3>
            @if ($panel['evidences']->isEmpty())
                <p class="muted">Aucune preuve visible.</p>
            @else
                <ul>
                    @foreach ($panel['evidences'] as $evidence)
                        <li>
                            <a href="{{ route('workspace.evidences.show', [$workspace, $evidence]) }}">{{ $evidence->title }}</a>
                            <span class="badge">{{ $evidence->typeLabel() }}</span>
                            <span class="muted">{{ $evidence->visibilityLabel() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <h3>Journal récent</h3>
            @if ($panel['dailyLogs']->isEmpty())
                <p class="muted">Aucune entrée de journal.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Travail réalisé (extrait)</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($panel['dailyLogs'] as $log)
                            <tr>
                                <td>{{ $log->work_date->format('d/m/Y') }}</td>
                                <td>{{ \Illuminate\Support\Str::limit((string) $log->work_done, 80, '…') }}</td>
                                <td><span class="badge">{{ $log->status }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach
@endsection
