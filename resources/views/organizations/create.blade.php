@extends('layouts.app')

@section('title', 'Nouvelle organisation — '.$workspace->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Organisations', 'url' => route('workspace.organizations.index', $workspace)],
        ['label' => 'Nouvelle organisation'],
    ]"/>
@endsection

@section('content')
    <div class="card">
        <h1>Nouvelle organisation</h1>

        <form method="POST" action="{{ route('workspace.organizations.store', $workspace) }}">
            @csrf
            @include('organizations._form')
            <button type="submit">Créer l'organisation</button>
        </form>
    </div>
@endsection
