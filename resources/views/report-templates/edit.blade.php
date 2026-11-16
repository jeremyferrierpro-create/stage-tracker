@extends('layouts.app')

@section('title', 'Modifier le modèle')

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Modèles de rapports', 'url' => route('workspace.report-templates.index', $workspace)],
        ['label' => $template->name],
    ]"/>
@endsection

@section('content')
    <h1>Modifier {{ $template->name }}</h1>

    <form method="POST" action="{{ route('workspace.report-templates.update', [$workspace, $template]) }}">
        @csrf
        @method('PUT')
        <label for="name">Nom</label>
        <input id="name" type="text" name="name" required maxlength="160" value="{{ old('name', $template->name) }}">

        <label for="type">Type</label>
        <select id="type" name="type" required>
            @foreach (\App\Domain\Reporting\Models\ReportTemplate::TYPES as $type)
                <option value="{{ $type }}" @selected(old('type', $template->type) === $type)>{{ \App\Domain\Reporting\Models\ReportTemplate::TYPE_LABELS[$type] }}</option>
            @endforeach
        </select>

        <label for="description">Description</label>
        <textarea id="description" name="description" rows="2" maxlength="5000">{{ old('description', $template->description) }}</textarea>

        <label for="status">Statut</label>
        <select id="status" name="status">
            @foreach (\App\Domain\Reporting\Models\ReportTemplate::STATUSES as $status)
                <option value="{{ $status }}" @selected(old('status', $template->status) === $status)>{{ $status }}</option>
            @endforeach
        </select>

        <div class="actions">
            <button type="submit">Enregistrer</button>
            <a class="button secondary" href="{{ route('workspace.report-templates.show', [$workspace, $template]) }}">Annuler</a>
        </div>
    </form>
@endsection
