@extends('layouts.app')

@section('title', $workspace->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name],
    ]"/>
@endsection

@section('content')
    <h1>{{ $workspace->name }}</h1>
    <p>
        <span class="badge">{{ $workspace->typeLabel() }}</span>
        @if ($membership)
            <span class="badge">{{ \App\Domain\Identity\Models\Workspace::roleLabel($membership->role) }}</span>
        @endif
    </p>

    @if (session('invitation_link'))
        <div class="card" style="border: 2px solid #f59e0b; background: rgba(245, 158, 11, 0.08); margin-bottom: 1.5rem;">
            <h2 style="color: #f59e0b; margin-top: 0;">🔗 Lien d'invitation direct généré</h2>
            <p>Transmettez ce lien directement à votre maître de stage / tuteur (par e-mail, messagerie ou SMS) :</p>
            <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                <input type="text" id="invitation-link-input-dash" value="{{ session('invitation_link') }}" readonly style="flex: 1; font-family: monospace;">
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('invitation-link-input-dash').value); alert('Lien copié dans le presse-papiers !');">Copier le lien</button>
            </div>
            <p class="muted" style="margin-top: 0.5rem; font-size: 0.85rem;">Ce lien est unique et valable pendant 7 jours. Dès son inscription via ce lien, votre tuteur sera automatiquement rattaché à votre stage.</p>
        </div>
    @endif

    @can('manageMembers', $workspace)
        <div class="card" style="margin-bottom: 1.5rem; border-left: 4px solid #f59e0b;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h2 style="margin: 0; font-size: 1.25rem;">🤝 Inviter mon maître de stage / tuteur</h2>
                    <p class="muted" style="margin: 0.25rem 0 0 0;">
                        Permettez à votre tuteur de suivre vos activités, valider vos journées de stage et attester vos compétences DWWM.
                    </p>
                </div>
                <form method="POST" action="{{ route('workspace.invitations.store', $workspace) }}" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                    @csrf
                    <input type="hidden" name="role" value="supervisor">
                    <input type="email" name="email" placeholder="email.tuteur@entreprise.fr" required style="min-width: 260px;">
                    <button type="submit" class="button">Générer le lien d'invitation</button>
                </form>
            </div>
        </div>
    @endcan

    <p class="muted">
        Vous êtes membre actif de cet espace. Toutes les données affichées ici
        sont filtrées par le serveur sur ce workspace (isolation multi-tenant).
    </p>

    <div class="grid">
        <div class="card">
            <h2>Stages</h2>
            <p><strong>{{ $internships->count() }}</strong> stage(s) au total —
                {{ $internships->where('status', 'active')->count() }} en cours,
                {{ $internships->where('status', 'completed')->count() }} terminé(s).
            </p>
            @if ($internships->isNotEmpty())
                <ul>
                    @foreach ($internships->take(5) as $internship)
                        <li>
                            <a href="{{ route('workspace.internships.show', [$workspace, $internship]) }}">
                                {{ $internship->title }}
                            </a>
                            <span class="muted">— {{ $internship->trainee->name }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <p>
                <a class="button secondary small" href="{{ route('workspace.internships.index', $workspace) }}">Voir les stages</a>
            </p>
        </div>

        <div class="card">
            <h2>Activités</h2>
            <p><strong>{{ $totalActivities }}</strong> activité(s) au total.</p>
            <ul>
                @foreach (\App\Domain\Project\Models\Activity::STATUSES as $status)
                    <li>
                        {{ \App\Domain\Project\Models\Activity::STATUS_LABELS[$status] }} :
                        <strong>{{ $activityCounts[$status] ?? 0 }}</strong>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="card">
        <h2>Journées récentes</h2>
        @if ($recentLogs->isEmpty())
            <p class="muted">Aucune journée saisie pour le moment.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Stage</th>
                        <th>Auteur</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentLogs as $log)
                        <tr>
                            <td>
                                <a href="{{ route('workspace.internships.daily_logs.show', [$workspace, $log->internship, $log]) }}">
                                    {{ $log->work_date->format('d/m/Y') }}
                                </a>
                            </td>
                            <td>{{ $log->internship->title }}</td>
                            <td>{{ $log->author->name }}</td>
                            <td><span class="badge">{{ $log->statusLabel() }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2>Raccourcis</h2>
        <div class="actions">
            <a class="button" href="{{ route('workspace.internships.index', $workspace) }}">Stages</a>
            @can('manageMembers', $workspace)
                <a class="button secondary" href="{{ route('workspace.competencies.index', $workspace) }}">Compétences</a>
                <a class="button secondary" href="{{ route('workspace.members.index', $workspace) }}">Membres et invitations</a>
            @endcan
            @can('viewAudit', $workspace)
                <a class="button secondary" href="{{ route('workspace.audit.index', $workspace) }}">Journal d'audit</a>
            @endcan
        </div>
    </div>
@endsection