@extends('layouts.app')

@section('title', $repository->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => $project->name, 'url' => route('workspace.internships.projects.show', [$workspace, $internship, $project])],
        ['label' => $repository->name],
    ]"/>
@endsection

@section('content')
    <h1>{{ $repository->name }}</h1>
    <p>
        <span class="badge">{{ $repository->provider }}</span>
        @if ($repository->hasToken()) <span class="badge">jeton enregistré (chiffré)</span> @endif
        @if ($repository->last_synced_at) <span class="muted">Dernière synchronisation : {{ $repository->last_synced_at->format('d/m/Y H:i') }}</span> @endif
    </p>

    <p class="muted">{{ $repository->repository_url }} @if ($repository->default_branch) — branche {{ $repository->default_branch }} @endif</p>

    <div class="actions">
        <form method="POST" action="{{ route('workspace.internships.projects.repositories.sync', [$workspace, $internship, $project, $repository]) }}" class="inline">
            @csrf
            <button type="submit">Synchroniser maintenant</button>
        </form>
        <form method="POST" action="{{ route('workspace.internships.projects.repositories.destroy', [$workspace, $internship, $project, $repository]) }}" class="inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="danger">Dissocier</button>
        </form>
    </div>

    @if ($errors->has('sync'))
        <p class="card error">{{ $errors->first('sync') }}</p>
    @endif

    @if ($syncRuns->isNotEmpty())
        <div class="card">
            <h2>Historique de synchronisation</h2>
            <table>
                <thead>
                    <tr>
                        <th>Début</th>
                        <th>Statut</th>
                        <th>Commits importés</th>
                        <th>Erreur</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($syncRuns as $run)
                        <tr>
                            <td>{{ $run->started_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td><span class="badge">{{ $run->status }}</span></td>
                            <td>{{ $run->items_imported }}</td>
                            <td>{{ $run->error_summary ? mb_strimwidth($run->error_summary, 0, 80, '…') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="card">
        <h2>Commits importés</h2>
        @if ($commits->isEmpty())
            <p class="muted">Aucun commit importé. Lancez une synchronisation.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>SHA</th>
                        <th>Message</th>
                        <th>Auteur</th>
                        <th>Date</th>
                        <th>Fichiers</th>
                        <th>Lier à une activité</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($commits as $commit)
                        <tr>
                            <td><code>{{ $commit->shortSha() }}</code></td>
                            <td>{{ mb_strimwidth($commit->message, 0, 60, '…') }}</td>
                            <td>{{ $commit->author_name ?? '—' }}</td>
                            <td>{{ $commit->committed_at->format('d/m/Y H:i') }}</td>
                            <td>
                                @if ($commit->files->isNotEmpty())
                                    {{ $commit->files->count() }} — {{ $commit->files->pluck('path')->implode(', ') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('workspace.internships.projects.repositories.link_activity', [$workspace, $internship, $project, $repository, $commit]) }}" class="inline">
                                    @csrf
                                    <select name="activity_id" required>
                                        <option value="">— activité —</option>
                                        @foreach ($activities as $activity)
                                            <option value="{{ $activity->id }}">{{ $activity->title }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="small">Lier</button>
                                </form>
                                @if ($commit->activities->isNotEmpty())
                                    <span class="muted">lié : {{ $commit->activities->pluck('title')->implode(', ') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
