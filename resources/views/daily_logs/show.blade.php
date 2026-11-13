@extends('layouts.app')

@section('title', 'Journal — '.$log->work_date->format('d/m/Y'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => 'Journal', 'url' => route('workspace.internships.daily_logs.index', [$workspace, $internship])],
        ['label' => $log->work_date->format('d/m/Y')],
    ]"/>
@endsection

@section('content')
    <h1>Journée du {{ $log->work_date->format('d/m/Y') }}</h1>
    <p><span class="badge">{{ $log->statusLabel() }}</span></p>

    <div class="card">
        @if ($log->start_time || $log->end_time)
            <p><strong>Horaires :</strong> {{ $log->start_time ?? '—' }} → {{ $log->end_time ?? '—' }}</p>
        @endif
        @if ($log->duration_minutes)
            <p><strong>Durée :</strong> {{ $log->duration_minutes }} min</p>
        @endif
        @foreach (['objectives' => 'Objectifs', 'work_done' => 'Travaux réalisés', 'difficulties' => 'Difficultés', 'solutions' => 'Solutions', 'results' => 'Résultats', 'remaining' => 'Reste à faire', 'private_notes' => 'Notes privées'] as $field => $label)
            @if ($log->{$field})
                <p><strong>{{ $label }} :</strong> {{ $log->{$field} }}</p>
            @endif
        @endforeach
    </div>

    <div class="card">
        <h2>Activités liées</h2>
        @if ($log->activities->isEmpty())
            <p class="muted">Aucune activité liée.</p>
        @else
            <ul>
                @foreach ($log->activities as $link)
                    <li>{{ $link->activity->title }} — {{ $link->contribution }}</li>
                @endforeach
            </ul>
        @endif

        @if (! $log->isClosed())
            <h3>Lier une activité</h3>
            <form method="POST" action="{{ route('workspace.internships.daily_logs.link_activity', [$workspace, $internship, $log]) }}" class="actions">
                @csrf
                <div>
                    <label>Activité</label>
                    <select name="activity_id" required>
                        @foreach ($activities as $activity)
                            <option value="{{ $activity->id }}">{{ $activity->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Contribution</label>
                    <input type="text" name="contribution" maxlength="5000">
                </div>
                <div style="align-self: end;">
                    <button type="submit" class="small">Lier</button>
                </div>
            </form>
        @endif
    </div>

    <div class="actions">
        @if (! $log->isClosed())
            <a class="button secondary" href="{{ route('workspace.internships.daily_logs.edit', [$workspace, $internship, $log]) }}">Modifier</a>
            <form method="POST" action="{{ route('workspace.internships.daily_logs.close', [$workspace, $internship, $log]) }}" class="inline">
                @csrf
                <button type="submit" class="danger">Clôturer la journée</button>
            </form>
        @endif
    </div>
@endsection