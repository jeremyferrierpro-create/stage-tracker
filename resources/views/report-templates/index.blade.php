@extends('layouts.app')

@section('title', 'Modèles de rapports - '.$workspace->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Modèles de rapports'],
    ]"/>
@endsection

@section('content')
    <h1>Modèles de rapports</h1>
    <p class="muted">
        Trames versionnées (aucune trame codée en dur). Une version publiée
        devient immuable et sert à la génération des rapports de stage.
    </p>

    <p>
        <a class="button" href="{{ route('workspace.report-templates.create', $workspace) }}">Nouveau modèle</a>
    </p>

    @if ($templates->isEmpty())
        <p class="muted">Aucun modèle pour cet espace.</p>
    @else
        <div class="card">
            <table>
                <thead>
                    <tr><th>Nom</th><th>Type</th><th>Statut</th><th>Versions</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($templates as $template)
                        <tr>
                            <td><a href="{{ route('workspace.report-templates.show', [$workspace, $template]) }}">{{ $template->name }}</a></td>
                            <td>{{ $template->typeLabel() }}</td>
                            <td><span class="badge">{{ $template->status }}</span></td>
                            <td>{{ $template->versions->count() }}</td>
                            <td></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
