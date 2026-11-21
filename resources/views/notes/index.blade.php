@extends('layouts.app')

@section('title', 'Pense-bête')

@section('breadcrumb')
    <x-breadcrumb :items="[['label' => 'Pense-bête']]" />
@endsection

@section('content')
    <div class="page-head"><h1>Pense-bête</h1></div>

    <div class="card">
        <h2>Nouvelle note</h2>
        <form method="POST" action="{{ route('workspace.notes.store', $workspace) }}" class="note-form">
            @csrf
            <div class="note-grid">
                <div>
                    <label for="note-title">Titre</label>
                    <input id="note-title" name="title" type="text" maxlength="200" required>
                </div>
                <div>
                    <label for="note-scope">Visibilité</label>
                    <select id="note-scope" name="scope">
                        <option value="personal">Personnelle</option>
                        <option value="workspace">Partagée dans l'espace</option>
                    </select>
                </div>
                <div>
                    <label for="note-due">Échéance</label>
                    <input id="note-due" name="due_at" type="datetime-local">
                </div>
                <div>
                    <label for="note-remind">Rappel à partir de</label>
                    <input id="note-remind" name="remind_at" type="datetime-local">
                </div>
                <div>
                    <label for="note-internship">Stage (optionnel)</label>
                    <select id="note-internship" name="internship_id">
                        <option value="">—</option>
                        @foreach ($internships as $internship)
                            <option value="{{ $internship->id }}">{{ $internship->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="note-project">Projet (optionnel)</label>
                    <select id="note-project" name="project_id">
                        <option value="">—</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label for="note-body">Détail</label>
                <textarea id="note-body" name="body" rows="2" maxlength="5000"></textarea>
            </div>
            <button type="submit" class="small">Ajouter</button>
        </form>
    </div>

    <div class="card">
        <form method="GET" class="actions note-filters">
            <select name="scope">
                <option value="">Toutes les portées</option>
                <option value="mine" @selected(($filters['scope'] ?? '') === 'mine')>Les miennes</option>
                <option value="shared" @selected(($filters['scope'] ?? '') === 'shared')>Partagées par les autres</option>
            </select>
            <select name="status">
                <option value="">Tous les statuts</option>
                <option value="todo" @selected(($filters['status'] ?? '') === 'todo')>À faire</option>
                <option value="done" @selected(($filters['status'] ?? '') === 'done')>Fait</option>
            </select>
            <label class="inline">
                <input type="checkbox" name="overdue" value="1" @checked(($filters['overdue'] ?? '') === '1')>
                En retard
            </label>
            <button type="submit" class="button secondary small">Filtrer</button>
        </form>

        <ul class="note-list">
            @forelse ($notes as $note)
                <li class="note-item {{ $note->isDone() ? 'is-done' : '' }}">
                    <form method="POST" action="{{ route('workspace.notes.toggle', [$workspace, $note]) }}">
                        @csrf
                        <button type="submit" class="note-check" title="Basculer fait/à faire">{{ $note->isDone() ? '☑' : '☐' }}</button>
                    </form>
                    <div class="note-content">
                        <strong>{{ $note->title }}</strong>
                        <span class="badge">{{ $note->scopeLabel() }}</span>
                        @if ($note->internship) <span class="badge">{{ $note->internship->title }}</span> @endif
                        @if ($note->project) <span class="badge">{{ $note->project->name }}</span> @endif
                        @if ($note->isOverdue()) <span class="badge overdue">En retard</span> @endif
                        @if ($note->due_at && ! $note->isDone())
                            <span class="muted small">Échéance : {{ $note->due_at->format('d/m/Y H:i') }}</span>
                        @endif
                        @if ($note->body)<p class="muted">{{ $note->body }}</p>@endif
                        <span class="muted small">par {{ $note->user->name }}</span>
                    </div>
                    <form method="POST" action="{{ route('workspace.notes.destroy', [$workspace, $note]) }}"
                          onsubmit="return confirm('Supprimer cette note ?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="link small">Supprimer</button>
                    </form>
                </li>
            @empty
                <li class="muted">Aucune note.</li>
            @endforelse
        </ul>
    </div>
@endsection
