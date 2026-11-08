@extends('layouts.app')

@section('title', 'Invitation')

@section('content')
    <div class="card">
        <h1>Invitation à rejoindre {{ $workspace->name }}</h1>

        <p>
            <strong>{{ $inviter?->name ?? 'Un administrateur' }}</strong>
            vous invite avec le rôle
            <span class="badge">{{ \App\Domain\Identity\Models\Workspace::roleLabel($invitation->role) }}</span>.
        </p>

        <p>
            Adresse invitée : <strong>{{ $invitation->email }}</strong><br>
            Cette invitation expire le {{ $invitation->expires_at->format('d/m/Y à H:i') }}.
        </p>

        @auth
            @if (strtolower(auth()->user()->email) === strtolower($invitation->email))
                <div class="actions">
                    <form method="POST" action="{{ route('invitations.accept', $token) }}">
                        @csrf
                        <button type="submit">Accepter l'invitation</button>
                    </form>

                    <form method="POST" action="{{ route('invitations.decline', $token) }}">
                        @csrf
                        <button type="submit" class="link">Refuser</button>
                    </form>
                </div>
            @else
                <p class="error">
                    Vous êtes connecté(e) avec {{ auth()->user()->email }}, qui ne correspond
                    pas à l'adresse invitée. Déconnectez-vous pour accepter cette invitation.
                </p>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="link">Se déconnecter</button>
                </form>
            @endif
        @else
            <h2>Créer votre compte</h2>
            <p class="muted">
                L'adresse e-mail est imposée par l'invitation et ne peut pas être modifiée.
            </p>

            <form method="POST" action="{{ route('invitations.register', $token) }}">
                @csrf

                <label for="name">Nom complet</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus>
                @error('name') <p class="error">{{ $message }}</p> @enderror

                <label for="password">Mot de passe (12 caractères minimum)</label>
                <input id="password" name="password" type="password" required>
                @error('password') <p class="error">{{ $message }}</p> @enderror

                <label for="password_confirmation">Confirmer le mot de passe</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required>

                <button type="submit">Créer mon compte et accepter</button>
            </form>

            <p class="muted">
                Vous avez déjà un compte ?
                <a href="{{ route('login', ['redirect' => route('invitations.show', $token)]) }}">Connectez-vous</a>
                pour accepter l'invitation.
            </p>
        @endauth
    </div>
@endsection
