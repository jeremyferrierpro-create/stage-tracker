@extends('layouts.app')

@section('title', 'Mot de passe')

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => 'Mon compte'],
        ['label' => 'Mot de passe'],
    ]"/>
@endsection

@section('content')
    <h1>Changer mon mot de passe</h1>

    <div class="card">
        <form method="POST" action="{{ route('account.password.update') }}">
            @csrf

            <label for="current_password">Mot de passe actuel</label>
            <input id="current_password" type="password" name="current_password" required autocomplete="current-password">

            <label for="password">Nouveau mot de passe (12 caractères minimum)</label>
            <input id="password" type="password" name="password" required minlength="12" autocomplete="new-password">

            <label for="password_confirmation">Confirmer le nouveau mot de passe</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required minlength="12" autocomplete="new-password">

            <div class="actions">
                <button type="submit">Enregistrer</button>
                <a class="button secondary" href="{{ route('workspaces.index') }}">Annuler</a>
            </div>
        </form>
    </div>
@endsection
