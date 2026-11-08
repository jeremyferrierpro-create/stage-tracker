@extends('layouts.app')

@section('title', 'Modifier '.$organization->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Organisations', 'url' => route('workspace.organizations.index', $workspace)],
        ['label' => 'Modifier '.$organization->name],
    ]"/>
@endsection

@section('content')
    <div class="card">
        <h1>Modifier {{ $organization->name }}</h1>

        <form method="POST"
              action="{{ route('workspace.organizations.update', [$workspace, $organization]) }}">
            @csrf
            @method('PUT')
            @include('organizations._form', ['organization' => $organization])
            <button type="submit">Enregistrer</button>
        </form>
    </div>
@endsection
