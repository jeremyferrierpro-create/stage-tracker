@extends('layouts.app')

@section('title', 'Preuves — '.$workspace->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Preuves'],
    ]"/>
@endsection

@section('content')
    <h1>Preuves de {{ $workspace->name }}</h1>
    <p class="muted">
        Éléments vérifiables (captures, documents, liens) avec une visibilité
        contrôlée : privée, stage ou partagée.
    </p>

    <p>
        <a class="button" href="{{ route('workspace.evidences.create', $workspace) }}">Nouvelle preuve</a>
    </p>

    @if ($evidences->isEmpty())
        <p class="muted">Aucune preuve pour cet espace.</p>
    @else
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Type</th>
                        <th>Visibilité</th>
                        <th>Propriétaire</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($evidences as $evidence)
                        <tr>
                            <td>{{ $evidence->title }}</td>
                            <td>{{ $evidence->typeLabel() }}</td>
                            <td><span class="badge">{{ $evidence->visibilityLabel() }}</span></td>
                            <td>{{ $evidence->owner->name }}</td>
                            <td>
                                <a class="button secondary small" href="{{ route('workspace.evidences.show', [$workspace, $evidence]) }}">Consulter</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection