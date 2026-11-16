@extends('layouts.app')

@section('title', 'Nouveau modèle de rapport')

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Modèles de rapports', 'url' => route('workspace.report-templates.index', $workspace)],
        ['label' => 'Nouveau'],
    ]"/>
@endsection

@section('content')
    <h1>Nouveau modèle de rapport</h1>

    <form method="POST" action="{{ route('workspace.report-templates.store', $workspace) }}">
        @csrf
        <label for="name">Nom</label>
        <input id="name" type="text" name="name" required maxlength="160" value="{{ old('name') }}">

        <label for="type">Type</label>
        <select id="type" name="type" required>
            @foreach (\App\Domain\Reporting\Models\ReportTemplate::TYPES as $type)
                <option value="{{ $type }}" @selected(old('type') === $type)>{{ \App\Domain\Reporting\Models\ReportTemplate::TYPE_LABELS[$type] }}</option>
            @endforeach
        </select>

        <label for="description">Description (optionnelle)</label>
        <textarea id="description" name="description" rows="2" maxlength="5000">{{ old('description') }}</textarea>

        <div class="actions">
            <button type="submit">Créer (brouillon)</button>
            <a class="button secondary" href="{{ route('workspace.report-templates.index', $workspace) }}">Annuler</a>
        </div>
    </form>
@endsection
