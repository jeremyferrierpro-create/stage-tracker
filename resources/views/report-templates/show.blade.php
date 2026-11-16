@extends('layouts.app')

@section('title', $template->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Modèles de rapports', 'url' => route('workspace.report-templates.index', $workspace)],
        ['label' => $template->name],
    ]"/>
@endsection

@section('content')
    <h1>{{ $template->name }}</h1>
    <p class="muted">
        Type : {{ $template->typeLabel() }} — statut : <span class="badge">{{ $template->status }}</span>
        @if ($template->description) — {{ $template->description }} @endif
    </p>

    <div class="actions">
        <a class="button secondary" href="{{ route('workspace.report-templates.edit', [$workspace, $template]) }}">Modifier le modèle</a>
        <form method="POST" action="{{ route('workspace.report-templates.destroy', [$workspace, $template]) }}" class="inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="danger" @if ($template->status === 'published') disabled @endif>Supprimer</button>
        </form>
    </div>

    <div class="card">
        <h2>Versions de trame</h2>

        <form method="POST" action="{{ route('workspace.report-templates.versions.store', [$workspace, $template]) }}" style="margin-bottom: 1rem;">
            @csrf
            <label for="version">Version (ex. v1.0.0)</label>
            <input id="version" type="text" name="version" required maxlength="40">

            <label for="title">Titre du rapport (optionnel)</label>
            <input id="title" type="text" name="title" maxlength="200">

            <label>Sections incluses</label>
            @foreach (\App\Domain\Reporting\Models\ReportTemplate::ALLOWED_SECTIONS as $section)
                <label style="font-weight: normal;">
                    <input type="checkbox" name="sections[]" value="{{ $section }}" checked>
                    {{ $section }}
                </label>
            @endforeach

            <div class="actions">
                <button type="submit" class="small">Ajouter la version</button>
            </div>
        </form>

        @if ($versions->isEmpty())
            <p class="muted">Aucune version pour ce modèle.</p>
        @else
            <table>
                <thead>
                    <tr><th>Version</th><th>Sections</th><th>Publiée le</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($versions as $version)
                        <tr>
                            <td>{{ $version->version }}</td>
                            <td>{{ implode(', ', $version->schema_json['sections'] ?? []) }}</td>
                            <td>{{ $version->published_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>
                                @if (! $version->isPublished())
                                    <form method="POST" action="{{ route('workspace.report-templates.versions.publish', [$workspace, $template, $version]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="small">Publier</button>
                                    </form>
                                @else
                                    <span class="badge">immuable</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
