@extends('layouts.app')

@php
    use Illuminate\Support\Str;
    $totalPlanned = collect($days)->sum('planned_minutes');
    $totalActual = collect($days)->sum('actual_minutes');
    $globalPercent = $totalPlanned > 0 ? min(100, (int) round($totalActual / $totalPlanned * 100)) : 0;
    $doneCount = collect($days)->where('status', 'done')->count();
@endphp

@section('title', 'Planning — '.$internship->title)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => 'Planning'],
    ]"/>
@endsection

@section('content')
    <h1>Planning vs réel — {{ $internship->title }}</h1>

    @if (empty($days))
        <p class="muted">
            Aucun planning importé pour ce stage.
            Utilisez <code>php artisan stage:import-planning</code> pour importer un fichier markdown.
        </p>
    @else
        <div class="card">
            <p>
                <strong>{{ count($days) }} jours</strong> —
                {{ $doneCount }} fait{{ $doneCount > 1 ? 's' : '' }} —
                prévu {{ $totalPlanned }} min, réel {{ $totalActual }} min,
                <strong>{{ $globalPercent }} %</strong> réalisé.
            </p>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Prévu</th>
                        <th>Réel</th>
                        <th>Écart</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($days as $day)
                        <tr>
                            <td>
                                {{ \Illuminate\Support\Carbon::parse($day['date'])->format('D d/m/Y') }}
                            </td>
                            <td>
                                <span class="badge">{{ \App\Domain\Planning\PlanningComparisonService::statusLabel($day['status']) }}</span>
                            </td>
                            <td>
                                @foreach ($day['entries'] as $slot => $entries)
                                    <div>
                                        <strong>{{ \App\Domain\Planning\Models\PlanEntry::SLOT_LABELS[$slot] ?? $slot }}</strong>
                                        <ul>
                                            @foreach ($entries as $entry)
                                                <li>{{ $entry->title }} <span class="muted">({{ $entry->planned_minutes }} min)</span></li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </td>
                            <td>
                                @forelse ($day['logs'] as $log)
                                    <div>
                                        <strong>{{ $log->author->name }}</strong>
                                        — {{ $log->duration_minutes !== null ? $log->duration_minutes.' min' : 'durée non renseignée' }}
                                        @if ($log->work_done)
                                            <br><span class="muted">{{ Str::limit($log->work_done, 160) }}</span>
                                        @endif
                                    </div>
                                @empty
                                    <span class="muted">Aucun journal</span>
                                @endforelse
                            </td>
                            <td>
                                {{ $day['planned_minutes'] }} → {{ $day['actual_minutes'] }} min
                                <br><strong>{{ $day['percent'] }} %</strong>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
