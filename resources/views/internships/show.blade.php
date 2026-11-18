@extends('layouts.app')

@section('title', $internship->title)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => $internship->title],
    ]"/>
@endsection

@section('content')
    <h1>{{ $internship->title }}</h1>
    <p>
        <span class="badge">{{ $internship->statusLabel() }}</span>
        <span class="muted">— {{ $internship->start_date->format('d/m/Y') }} → {{ $internship->end_date->format('d/m/Y') }}</span>
    </p>

    <div class="card">
        <h2>Informations</h2>
        <p><strong>Stagiaire :</strong> {{ $internship->trainee->name }}</p>
        <p><strong>Entreprise d'accueil :</strong> {{ $internship->company_name }}</p>
        @if ($internship->objectives)
            <p><strong>Objectifs :</strong> {{ $internship->objectives }}</p>
        @endif
    </div>

    @can('manageInternships', $workspace)
        <p>
            <a class="button secondary" href="{{ route('workspace.internships.edit', [$workspace, $internship]) }}">Modifier le stage</a>
            <a class="button secondary" href="{{ route('workspace.internships.projects.index', [$workspace, $internship]) }}">Projets</a>
        </p>
    @else
        <p>
            <a class="button secondary" href="{{ route('workspace.internships.projects.index', [$workspace, $internship]) }}">Projets</a>
            <a class="button secondary" href="{{ route('workspace.internships.daily_logs.index', [$workspace, $internship]) }}">Journal</a>
        </p>
    @endcan

    <p>
        <a class="button secondary" href="{{ route('workspace.internships.competencies', [$workspace, $internship]) }}">Matrice des compétences</a>
        <a class="button secondary" href="{{ route('workspace.internships.reports.index', [$workspace, $internship]) }}">Rapports</a>
    </p>

    <div class="card">
        <h2>Participants</h2>

        @if ($internship->participants->isEmpty())
            <p class="muted">Aucun participant enregistré.</p>
        @else
            <table>
                <thead>
                    <tr><th>Nom</th><th>Rôle</th><th>Statut</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($internship->participants as $participant)
                        <tr>
                            <td>{{ $participant->user->name }}</td>
                            <td>{{ $participant->roleLabel() }}</td>
                            <td>
                                <span class="badge">{{ $participant->status === 'active' ? 'actif' : 'révoqué' }}</span>
                            </td>
                            <td>
                                @can('manageInternships', $workspace)
                                    @if ($participant->status === 'active')
                                        @if ($participant->role !== 'trainee')
                                            <form method="POST" action="{{ route('workspace.internships.participants.destroy', [$workspace, $internship, $participant]) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="danger small">Révoquer</button>
                                            </form>
                                        @endif
                                    @else
                                        <form method="POST" action="{{ route('workspace.internships.participants.reactivate', [$workspace, $internship, $participant]) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="small">Réactiver</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @can('manageInternships', $workspace)
            <h3>Ajouter un participant</h3>
            <form method="POST" action="{{ route('workspace.internships.participants.store', [$workspace, $internship]) }}" class="actions">
                @csrf
                <div>
                    <label for="participant-user">Utilisateur</label>
                    <select id="participant-user" name="user_id" required>
                        @foreach ($workspace->users()->wherePivot('status', 'active')->orderBy('name')->get() as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="participant-role">Rôle</label>
                    <select id="participant-role" name="role" required>
                        @foreach (\App\Domain\Internship\Models\InternshipParticipant::ROLES as $role)
                            <option value="{{ $role }}">{{ \App\Domain\Internship\Models\InternshipParticipant::ROLE_LABELS[$role] }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="align-self: end;">
                    <button type="submit" class="small">Ajouter</button>
                </div>
            </form>
        @endcan
    </div>
@endsection