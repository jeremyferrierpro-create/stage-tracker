@extends('layouts.app')

@section('title', 'Journal — '.$internship->title)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => 'Journal'],
    ]"/>
@endsection

@section('content')
    <h1>Journal du stage « {{ $internship->title }} »</h1>

    <p>
        <a class="button" href="{{ route('workspace.internships.daily_logs.create', [$workspace, $internship]) }}">Nouvelle journée</a>
    </p>

    @if ($logs->isEmpty())
        <p class="muted">Aucune journée saisie.</p>
    @else
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Auteur</th>
                        <th>Durée</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td>{{ $log->work_date->format('d/m/Y') }}</td>
                            <td>{{ $log->author->name }}</td>
                            <td>{{ $log->duration_minutes ? $log->duration_minutes.' min' : '—' }}</td>
                            <td><span class="badge">{{ $log->statusLabel() }}</span></td>
                            <td>
                                <a class="button secondary small" href="{{ route('workspace.internships.daily_logs.show', [$workspace, $internship, $log]) }}">Consulter</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection