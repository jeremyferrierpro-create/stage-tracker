@extends('layouts.app')

@section('title', 'Connexion')

@section('content')
    <div class="card">
        <h1>Connexion</h1>
        <p class="muted">Accédez à vos espaces de travail.</p>

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <label for="email">Adresse e-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}"
                   required autofocus autocomplete="username">

            @error('email')
                <p class="error">{{ $message }}</p>
            @enderror

            <label for="password">Mot de passe</label>
            <input id="password" name="password" type="password"
                   required autocomplete="current-password">

            @error('password')
                <p class="error">{{ $message }}</p>
            @enderror

            <label class="remember" for="remember">
                <input id="remember" name="remember" type="checkbox" value="1">
                Rester connecté
            </label>

            <button type="submit">Se connecter</button>
        </form>

        <p class="muted" style="margin-top: 1.5rem; font-size: .9rem;">
            Aucune inscription publique : les comptes sont créés par un administrateur.
        </p>
    </div>
@endsection
