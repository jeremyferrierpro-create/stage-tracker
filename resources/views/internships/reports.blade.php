@extends('layouts.app')

@section('title', 'Rapports - '.$internship->title)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => 'Rapports'],
    ]"/>
@endsection

@section('content')
    <h1>Rapports — {{ $internship->title }}</h1>
    <p class="muted">
        Les rapports sont filtrés par visibilité : aucune donnée privée
        (preuves privées, notes personnelles, commentaires privés) n'y figure ;
        les exclusions sont listées dans le document et auditées.
    </p>

    @if ($canGenerate && $templates->isNotEmpty())
    <div class="card">
        <h2>Générer un rapport</h2>
        <form method="POST" action="{{ route('workspace.internships.reports.store', [$workspace, $internship]) }}">
            @csrf
            <label for="template-version">Trame (versions publiées uniquement)</label>
            <select id="template-version" name="template_version_id" required>
                <option value="">— choisir une trame —</option>
                @foreach ($templates as $template)
                    <optgroup label="{{ $template->name }}">
                        @foreach ($template->versions as $version)
                            <option value="{{ $version->id }}">{{ $template->typeLabel() }} — {{ $version->version }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <div class="actions">
                <button type="submit">Générer (asynchrone)</button>
            </div>
        </form>
    </div>
    @elseif ($canGenerate)
        <p class="muted">Aucune trame publiée : demandez à un administrateur de créer un modèle de rapports.</p>
    @endif

    <div class="card">
        <h2>Rapports du stage</h2>
        @if ($reports->isEmpty())
            <p class="muted">Aucun rapport généré.</p>
        @else
            <table>
                <thead>
                    <tr><th>Trame</th><th>Statut</th><th>Généré par</th><th>Le</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($reports as $report)
                        <tr>
                            <td>
                                {{ $report->templateVersion->template->name }}
                                <span class="muted">({{ $report->templateVersion->version }})</span>
                            </td>
                            <td><span class="badge">{{ $report->statusLabel() }}</span></td>
                            <td>{{ $report->generator->name }}</td>
                            <td>{{ $report->generated_at->format('d/m/Y H:i') }}</td>
                            <td>
                                @if ($report->status === 'generated')
                                    <a class="button secondary small" href="{{ route('workspace.internships.reports.download', [$workspace, $internship, $report]) }}">HTML</a>
                                    <a class="button secondary small" href="{{ route('workspace.internships.reports.export', [$workspace, $internship, $report, 'pdf']) }}">PDF</a>
                                    <a class="button secondary small" href="{{ route('workspace.internships.reports.export', [$workspace, $internship, $report, 'docx']) }}">DOCX</a>
                                @elseif ($report->status === 'failed' && $canGenerate)
                                    <form method="POST" action="{{ route('workspace.internships.reports.retry', [$workspace, $internship, $report]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="small">Relancer</button>
                                    </form>
                                @endif

                                @if ($canGenerate)
                                    <form method="POST" action="{{ route('workspace.internships.reports.destroy', [$workspace, $internship, $report]) }}" class="inline" onsubmit="return confirm('Supprimer ce rapport ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="danger small">Supprimer</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
