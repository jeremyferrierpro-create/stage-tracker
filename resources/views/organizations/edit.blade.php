@extends('layouts.app')

@section('title', 'Modifier '.$organization->name)

@section('content')
    <p><a href="{{ route('workspace.organizations.index', $workspace) }}">← Organisations</a></p>

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
