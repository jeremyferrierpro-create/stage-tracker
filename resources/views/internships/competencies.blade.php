@extends('layouts.app')

@section('title', 'Matrice des compétences')

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => 'Compétences'],
    ]"/>
@endsection

@section('content')
    <h1>Matrice des compétences — {{ $internship->title }}</h1>
    <p class="muted">
        Distinction entre compétence <strong>mobilisée</strong> (associée à une
        activité avec justification), <strong>documentée</strong> (marquée par le
        gestionnaire) et <strong>évaluée</strong> (appréciation nominative d'un
        maître de stage ou tuteur). Aucune validation automatique.
    </p>

    @if ($rows->isEmpty())
        <p class="card">Aucune compétence associée pour ce stage.</p>
    @else
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Compétence</th>
                        <th>Statut</th>
                        <th>Activité</th>
                        <th>Justification</th>
                        <th>Dernière appréciation</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>
                                <strong>{{ $row->competency->code }}</strong><br>
                                {{ $row->competency->title }}
                                @if ($row->competency->block)
                                    <span class="muted">({{ $row->competency->block->title }})</span>
                                @endif
                            </td>
                            <td><span class="badge">{{ $row->statusLabel() }}</span></td>
                            <td>
                                <a href="{{ route('workspace.internships.projects.activities.show', [$workspace, $internship, $row->activity->project, $row->activity]) }}">
                                    {{ $row->activity->title }}
                                </a>
                            </td>
                            <td>{{ $row->justification }}</td>
                            <td>
                                @php $latest = $row->assessments->sortByDesc('assessed_at')->first(); @endphp
                                @if ($latest)
                                    <span class="badge">{{ $latest->statusLabel() }}</span>
                                    par {{ $latest->assessor->name }}
                                    le {{ $latest->assessed_at->format('d/m/Y') }}<br>
                                    <span class="muted">« {{ $latest->appreciation }} »</span>
                                @else
                                    <span class="muted">Aucune</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
