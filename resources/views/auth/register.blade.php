@extends('layouts.app')

@section('title', 'Inscription Stagiaire DWWM')

@section('content')
    <div class="card auth-card">
        <h1>Créer mon espace Stagiaire</h1>
        <p class="muted">
            Accès libre pour les candidats au Titre Professionnel Développeur Web et Web Mobile.
            Votre espace sera automatiquement configuré avec les 6 compétences officielles.
        </p>

        <form method="POST" action="{{ route('register.store') }}">
            @csrf

            <label for="name">Nom complet (Prénom et Nom)</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}"
                   required autofocus autocomplete="name" placeholder="Ex. Thomas Martin">
            @error('name')
                <p class="error">{{ $message }}</p>
            @enderror

            <label for="email">Adresse e-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}"
                   required autocomplete="email" placeholder="thomas.martin@example.com">
            @error('email')
                <p class="error">{{ $message }}</p>
            @enderror

            <label for="password">Mot de passe (12 caractères minimum)</label>
            <input id="password" name="password" type="password"
                   required minlength="12" autocomplete="new-password">
            @error('password')
                <p class="error">{{ $message }}</p>
            @enderror

            <label for="password_confirmation">Confirmer le mot de passe</label>
            <input id="password_confirmation" name="password_confirmation" type="password"
                   required minlength="12" autocomplete="new-password">

            <div class="actions" style="margin-top: 1.5rem;">
                <button type="submit" class="button">Créer mon espace de stage</button>
                <a class="button secondary" href="{{ route('login') }}">Déjà inscrit ? Se connecter</a>
            </div>
        </form>
    </div>
@endsection
