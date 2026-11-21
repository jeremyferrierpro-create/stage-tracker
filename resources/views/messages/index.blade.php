@extends('layouts.app')

@section('title', 'Messages')

@section('breadcrumb')
    <x-breadcrumb :items="[['label' => 'Messages']]" />
@endsection

@section('content')
    <div class="page-head"><h1>Messages</h1></div>

    <div class="card">
        <h2>Nouveau message</h2>
        <form method="POST" action="{{ route('workspace.messages.store', $workspace) }}" class="actions">
            @csrf
            <div>
                <label for="msg-type">Type</label>
                <select id="msg-type" name="type" required>
                    <option value="direct">Message direct</option>
                    <option value="group">Groupe</option>
                </select>
            </div>
            <div>
                <label for="msg-user">Destinataire</label>
                <select id="msg-user" name="user_id">
                    <option value="">— choisir —</option>
                    @foreach ($members as $member)
                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="msg-title">Titre du groupe (optionnel)</label>
                <input id="msg-title" name="title" type="text" maxlength="120">
            </div>
            <button type="submit" class="small">Commencer</button>
        </form>
        <p class="muted small">Pour un groupe, sélectionnez le destinataire puis renommez le titre. Les fils de stage et de projet s'ouvrent depuis leur page dédiée.</p>
    </div>

    <div class="card">
        <h2>Conversations</h2>
        @forelse ($conversations as $conversation)
            <a class="conv-item" href="{{ route('workspace.messages.show', [$workspace, $conversation]) }}">
                <span class="conv-title">{{ $conversation->displayTitle() }}</span>
                <span class="badge">{{ $conversation->typeLabel() }}</span>
                @if (($unread[$conversation->id] ?? 0) > 0)
                    <span class="badge unread">{{ $unread[$conversation->id] }} non lu(s)</span>
                @endif
            </a>
        @empty
            <p class="muted">Aucune conversation pour le moment.</p>
        @endforelse
    </div>
@endsection
