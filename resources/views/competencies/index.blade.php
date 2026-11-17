@extends('layouts.app')

@section('title', 'Compétences - '.$workspace->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Compétences'],
    ]"/>
@endsection

@section('content')
    <h1>Compétences de {{ $workspace->name }}</h1>
    <p class="muted">
        Liste simple définie par les administrateurs. Ces compétences peuvent
        être associées aux activités (avec justification) puis évaluées
        nominativement par le maître de stage ou le tuteur.
    </p>

    <p>
        <a class="button" href="{{ route('workspace.competencies.create', $workspace) }}">Nouvelle compétence</a>
    </p>

    @if ($competencies->isEmpty())
        <p class="muted">Aucune compétence définie pour cet espace.</p>
    @else
        <div class="card">
            <table>
                <thead>
                    <tr><th>Code</th><th>Intitulé</th><th>Description</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($competencies as $competency)
                        <tr>
                            <td><strong>{{ $competency->code }}</strong></td>
                            <td>{{ $competency->title }}</td>
                            <td class="muted">{{ $competency->description ?? '—' }}</td>
                            <td>
                                @unless ($competency->associations()->exists())
                                    <form method="POST" action="{{ route('workspace.competencies.destroy', [$workspace, $competency]) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="danger small">Supprimer</button>
                                    </form>
                                @else
                                    <span class="badge">associée</span>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
