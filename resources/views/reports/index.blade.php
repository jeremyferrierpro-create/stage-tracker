@extends('layouts.app')

@section('title', 'Rapports - '.$workspace->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Rapports'],
    ]"/>
@endsection

@section('content')
    <h1>Rapports — {{ $workspace->name }}</h1>
    <p class="muted">
        Tous les rapports générés dans l'espace. Le contenu est filtré par
        visibilité (INV-14) et chaque téléchargement est audité.
    </p>

    <div class="card">
        @if ($reports->isEmpty())
            <p class="muted">
                Aucun rapport généré. Ouvrez un stage puis « Rapports » pour en créer un.
            </p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Stage</th>
                        <th>Trame</th>
                        <th>Statut</th>
                        <th>Généré par</th>
                        <th>Le</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reports as $report)
                        <tr>
                            <td>
                                <a href="{{ route('workspace.internships.show', [$workspace, $report->internship]) }}">
                                    {{ $report->internship->title }}
                                </a>
                            </td>
                            <td>
                                {{ $report->templateVersion->template->name }}
                                <span class="muted">({{ $report->templateVersion->version }})</span>
                            </td>
                            <td><span class="badge">{{ $report->statusLabel() }}</span></td>
                            <td>{{ $report->generator->name }}</td>
                            <td>{{ $report->generated_at->format('d/m/Y H:i') }}</td>
                            <td>
                                @if ($report->status === 'generated')
                                    <a class="button secondary small" href="{{ route('workspace.internships.reports.download', [$workspace, $report->internship, $report]) }}">HTML</a>
                                    <a class="button secondary small" href="{{ route('workspace.internships.reports.export', [$workspace, $report->internship, $report, 'pdf']) }}">PDF</a>
                                    <a class="button secondary small" href="{{ route('workspace.internships.reports.export', [$workspace, $report->internship, $report, 'docx']) }}">DOCX</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $reports->links() }}
        @endif
    </div>
@endsection
