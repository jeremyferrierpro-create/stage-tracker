@extends('layouts.app')

@section('title', 'Formations — '.$workspace->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Formations'],
    ]"/>
@endsection

@section('content')
    <h1>Formations de {{ $workspace->name }}</h1>
    <p class="muted">
        Programmes de formation et inscriptions. Un programme est rattaché à une
        organisation de l'espace ; les inscriptions sont uniques par
        (utilisateur, programme).
    </p>

    @can('manageTraining', $workspace)
        <p>
            <a class="button" href="{{ route('workspace.training.create', $workspace) }}">
                Nouveau programme
            </a>
        </p>
    @endcan

    <div class="card">
        <h2>Programmes</h2>

        @if ($programs->isEmpty())
            <p class="muted">Aucun programme de formation pour cet espace.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Organisation</th>
                        <th>Période</th>
                        <th>Certification</th>
                        <th>Inscriptions</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($programs as $program)
                        <tr>
                            <td>{{ $program->name }}</td>
                            <td>{{ $program->organization->name }}</td>
                            <td>
                                @if ($program->start_date || $program->end_date)
                                    {{ $program->start_date?->format('d/m/Y') ?? '—' }}
                                    → {{ $program->end_date?->format('d/m/Y') ?? '—' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $program->certification ?? '—' }}</td>
                            <td>{{ $program->enrollments_count }}</td>
                            <td>
                                @can('manageTraining', $workspace)
                                    <a class="button secondary small" href="{{ route('workspace.training.edit', [$workspace, $program]) }}">
                                        Gérer
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection