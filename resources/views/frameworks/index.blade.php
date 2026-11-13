@extends('layouts.app')

@section('title', 'Référentiels de compétences')

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => 'Référentiels'],
    ]"/>
@endsection

@section('content')
    <h1>Référentiels de compétences</h1>
    <p class="muted">
        Ressource partagée entre tous les espaces (DEC-ST-003) : aucun
        référentiel n'est codé en dur, les versions publiées sont immuables
        (INV-08). Administration réservée aux propriétaires et administrateurs
        d'un espace actif.
    </p>

    @can('manage', \App\Domain\Framework\Models\CompetencyFramework::class)
        <p>
            <a class="button" href="{{ route('frameworks.create') }}">Nouveau référentiel</a>
        </p>
    @endcan

    <div class="card">
        <h2>Référentiels</h2>

        @if ($frameworks->isEmpty())
            <p class="muted">Aucun référentiel saisi pour le moment.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Organisme émetteur</th>
                        <th>Statut</th>
                        <th>Versions</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($frameworks as $framework)
                        <tr>
                            <td>{{ $framework->name }}</td>
                            <td>{{ $framework->issuing_authority ?? '—' }}</td>
                            <td><span class="badge">{{ $framework->statusLabel() }}</span></td>
                            <td>{{ $framework->versions_count }}</td>
                            <td>
                                <a class="button secondary small" href="{{ route('frameworks.show', $framework) }}">Consulter</a>
                                @can('manage', $framework)
                                    <a class="button secondary small" href="{{ route('frameworks.edit', $framework) }}">Modifier</a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection