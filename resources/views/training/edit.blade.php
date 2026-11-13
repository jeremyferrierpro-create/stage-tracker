@extends('layouts.app')

@section('title', 'Gérer '.$program->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Formations', 'url' => route('workspace.training.index', $workspace)],
        ['label' => $program->name],
    ]"/>
@endsection

@section('content')
    <h1>{{ $program->name }}</h1>

    <div class="card">
        <h2>Paramètres du programme</h2>

        <form method="POST" action="{{ route('workspace.training.update', [$workspace, $program]) }}">
            @csrf
            @method('PUT')

            <label for="organization_id">Organisation</label>
            <select id="organization_id" name="organization_id" required>
                @foreach ($organizations as $organization)
                    <option value="{{ $organization->id }}" @selected(old('organization_id', $program->organization_id) === $organization->id)>
                        {{ $organization->name }}
                    </option>
                @endforeach
            </select>
            @error('organization_id') <p class="error">{{ $message }}</p> @enderror

            <label for="name">Nom</label>
            <input id="name" type="text" name="name" required maxlength="160" value="{{ old('name', $program->name) }}">
            @error('name') <p class="error">{{ $message }}</p> @enderror

            <label for="certification">Certification visée</label>
            <input id="certification" type="text" name="certification" maxlength="160" value="{{ old('certification', $program->certification) }}">
            @error('certification') <p class="error">{{ $message }}</p> @enderror

            <label for="start_date">Date de début</label>
            <input id="start_date" type="date" name="start_date" value="{{ old('start_date', optional($program->start_date)->format('Y-m-d')) }}">
            @error('start_date') <p class="error">{{ $message }}</p> @enderror

            <label for="end_date">Date de fin</label>
            <input id="end_date" type="date" name="end_date" value="{{ old('end_date', optional($program->end_date)->format('Y-m-d')) }}">
            @error('end_date') <p class="error">{{ $message }}</p> @enderror

            <div class="actions">
                <button type="submit">Enregistrer</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Inscriptions</h2>

        @if ($enrollments->isEmpty())
            <p class="muted">Aucune inscription pour ce programme.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Participant</th>
                        <th>Période</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($enrollments as $enrollment)
                        <tr>
                            <td>{{ $enrollment->user->name }} ({{ $enrollment->user->email }})</td>
                            <td>
                                {{ $enrollment->start_date->format('d/m/Y') }} →
                                {{ $enrollment->end_date->format('d/m/Y') }}
                            </td>
                            <td>
                                <span class="badge">{{ $enrollment->statusLabel() }}</span>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('workspace.training.enrollments.status', [$workspace, $program, $enrollment]) }}" class="inline">
                                    @csrf
                                    <select name="status">
                                        @foreach (\App\Domain\Training\Models\TrainingEnrollment::STATUSES as $status)
                                            <option value="{{ $status }}" @selected($enrollment->status === $status)>
                                                {{ \App\Domain\Training\Models\TrainingEnrollment::STATUS_LABELS[$status] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="small">Mettre à jour</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h3>Ajouter une inscription</h3>
        <form method="POST" action="{{ route('workspace.training.enrollments.store', [$workspace, $program]) }}">
            @csrf

            <label for="user_id">Participant (membre actif de l'espace)</label>
            <select id="user_id" name="user_id" required>
                @foreach ($candidates as $candidate)
                    <option value="{{ $candidate->id }}">{{ $candidate->name }} ({{ $candidate->email }})</option>
                @endforeach
            </select>
            @error('user_id') <p class="error">{{ $message }}</p> @enderror

            <label for="enroll_start">Date de début</label>
            <input id="enroll_start" type="date" name="start_date" required value="{{ old('start_date', now()->format('Y-m-d')) }}">
            @error('start_date') <p class="error">{{ $message }}</p> @enderror

            <label for="enroll_end">Date de fin</label>
            <input id="enroll_end" type="date" name="end_date" required value="{{ old('end_date', now()->addMonths(8)->format('Y-m-d')) }}">
            @error('end_date') <p class="error">{{ $message }}</p> @enderror

            <div class="actions">
                <button type="submit">Inscrire</button>
            </div>
        </form>
    </div>
@endsection