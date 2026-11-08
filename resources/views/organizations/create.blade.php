@extends('layouts.app')

@section('title', 'Nouvelle organisation — '.$workspace->name)

@section('content')
    <p><a href="{{ route('workspace.organizations.index', $workspace) }}">← Organisations</a></p>

    <div class="card">
        <h1>Nouvelle organisation</h1>

        <form method="POST" action="{{ route('workspace.organizations.store', $workspace) }}">
            @csrf
            @include('organizations._form')
            <button type="submit">Créer l'organisation</button>
        </form>
    </div>
@endsection
