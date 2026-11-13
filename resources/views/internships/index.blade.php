@extends('layouts.app')

@section('title', 'Stages — '.$workspace->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages'],
    ]"/>
@endsection

@section('content')
    <h1>Stages de {{ $workspace->name }}</h1>

    @can('manageInternships', $workspace)
        <p>
            <a class="button" href="{{ route('workspace.internships.create', $workspace) }}">Nouveau stage</a>
        </p>
    @endcan

    @if ($internships->isEmpty())
        <p class="muted">Aucun stage pour cet espace.</p>
    @else
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Stagiaire</th>
                        <th>Organisation d'accueil</th>
                        <th>Période</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($internships as $internship)
                        <tr>
                            <td>{{ $internship->title }}</td>
                            <td>{{ $internship->trainee->name }}</td>
                            <td>{{ $internship->hostOrganization->name }}</td>
                            <td>{{ $internship->start_date->format('d/m/Y') }} → {{ $internship->end_date->format('d/m/Y') }}</td>
                            <td><span class="badge">{{ $internship->statusLabel() }}</span></td>
                            <td>
                                <a class="button secondary small" href="{{ route('workspace.internships.show', [$workspace, $internship]) }}">Consulter</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection