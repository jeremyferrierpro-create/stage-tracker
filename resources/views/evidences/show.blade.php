@extends('layouts.app')

@section('title', $evidence->title)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Preuves', 'url' => route('workspace.evidences.index', $workspace)],
        ['label' => $evidence->title],
    ]"/>
@endsection

@section('content')
    <h1>{{ $evidence->title }}</h1>
    <p>
        <span class="badge">{{ $evidence->typeLabel() }}</span>
        <span class="badge">{{ $evidence->visibilityLabel() }}</span>
    </p>

    <div class="card">
        @if ($evidence->description)
            <p><strong>Description :</strong> {{ $evidence->description }}</p>
        @endif
        <p><strong>Ce que la preuve démontre :</strong> {{ $evidence->demonstrated }}</p>
        @if ($evidence->storage_key)
            <p><strong>Fichier :</strong> {{ basename($evidence->storage_key) }}</p>
            <p>
                <a class="button small" href="{{ route('workspace.evidences.download', [$workspace, $evidence]) }}">Télécharger</a>
            </p>
        @endif
        @if ($evidence->external_url)
            <p><strong>Lien :</strong> <a href="{{ $evidence->external_url }}" target="_blank" rel="noopener">{{ $evidence->external_url }}</a></p>
        @endif
        @if ($evidence->captured_at)
            <p><strong>Capturée le :</strong> {{ $evidence->captured_at->format('d/m/Y') }}</p>
        @endif
        <p><strong>Propriétaire :</strong> {{ $evidence->owner->name }}</p>
    </div>

    <div class="card">
        <h2>Activités liées</h2>
        @if ($evidence->activities->isEmpty())
            <p class="muted">Aucune activité liée.</p>
        @else
            <ul>
                @foreach ($evidence->activities as $activity)
                    <li>{{ $activity->title }} @if ($activity->pivot->note) — {{ $activity->pivot->note }} @endif</li>
                @endforeach
            </ul>
        @endif

        <h3>Lier à une activité</h3>
        <form method="POST" action="{{ route('workspace.evidences.link_activity', [$workspace, $evidence]) }}" class="actions">
            @csrf
            <div>
                <label>Activité</label>
                <select name="activity_id" required>
                    @foreach (\App\Domain\Project\Models\Activity::query()->where('workspace_id', $workspace->id)->orderBy('title')->get() as $activity)
                        <option value="{{ $activity->id }}">{{ $activity->title }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Note</label>
                <input type="text" name="note" maxlength="5000">
            </div>
            <div style="align-self: end;">
                <button type="submit" class="small">Lier</button>
            </div>
        </form>
    </div>

    <div class="actions">
        @can('manage', $evidence)
            <a class="button secondary" href="{{ route('workspace.evidences.edit', [$workspace, $evidence]) }}">Modifier</a>
        @endcan
        @can('manage', $evidence)
            <form method="POST" action="{{ route('workspace.evidences.archive', [$workspace, $evidence]) }}" class="inline">
                @csrf
                <button type="submit" class="danger small">Archiver</button>
            </form>
        @endcan
    </div>
@endsection