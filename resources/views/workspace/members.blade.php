@extends('layouts.app')

@section('title', 'Membres — '.$workspace->name)

@section('content')
    <p><a href="{{ route('workspace.dashboard', $workspace) }}">← Tableau de bord</a></p>

    <h1>Membres de {{ $workspace->name }}</h1>

    @if ($assignableRoles !== [])
        <div class="card">
            <h2>Inviter un participant</h2>

            <form method="POST" action="{{ route('workspace.invitations.store', $workspace) }}">
                @csrf

                <label for="email">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required>
                @error('email') <p class="error">{{ $message }}</p> @enderror

                <label for="role">Rôle proposé</label>
                <select id="role" name="role" required>
                    @foreach ($assignableRoles as $role)
                        <option value="{{ $role }}" @selected(old('role') === $role)>
                            {{ \App\Domain\Identity\Models\Workspace::roleLabel($role) }}
                        </option>
                    @endforeach
                </select>
                @error('role') <p class="error">{{ $message }}</p> @enderror

                <button type="submit">Envoyer l'invitation</button>
            </form>

            <p class="muted">
                Le lien est envoyé par e-mail et expire après 7 jours. Seul son hash
                est conservé en base. L'invité reçoit exactement le rôle proposé.
            </p>
        </div>
    @endif

    <div class="card">
        <h2>Membres</h2>

        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>E-mail</th>
                    <th>Rôle</th>
                    <th>Statut</th>
                    <th>Depuis</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($members as $member)
                    <tr>
                        <td>{{ $member->user?->name ?? '—' }}</td>
                        <td>{{ $member->user?->email ?? '—' }}</td>
                        <td>{{ \App\Domain\Identity\Models\Workspace::roleLabel($member->role) }}</td>
                        <td>{{ $member->status }}</td>
                        <td>{{ $member->joined_at?->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">Aucun membre.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Invitations en attente</h2>

        <table>
            <thead>
                <tr>
                    <th>E-mail</th>
                    <th>Rôle</th>
                    <th>Invité par</th>
                    <th>Expire le</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pendingInvitations as $invitation)
                    <tr>
                        <td>{{ $invitation->email }}</td>
                        <td>{{ \App\Domain\Identity\Models\Workspace::roleLabel($invitation->role) }}</td>
                        <td>{{ $invitation->inviter?->name ?? '—' }}</td>
                        <td>{{ $invitation->expires_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <form method="POST"
                                  action="{{ route('workspace.invitations.destroy', [$workspace, $invitation]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="link">Annuler</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">Aucune invitation en attente.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
