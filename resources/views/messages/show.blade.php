@extends('layouts.app')

@section('title', $conversation->displayTitle())

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Messages', 'url' => route('workspace.messages.index', $workspace)],
        ['label' => $conversation->displayTitle()],
    ]" />
@endsection

@section('content')
    <div class="page-head">
        <h1>{{ $conversation->displayTitle() }}</h1>
        <span class="badge">{{ $conversation->typeLabel() }}</span>
    </div>

    <div class="card chat" data-chat-poll>
        <div class="chat-thread">
            @forelse ($messages as $message)
                <div class="msg {{ $message->user_id === auth()->id() ? 'mine' : '' }}">
                    <div class="bubble">
                        @if ($message->user_id !== auth()->id())
                            <span class="msg-author">{{ $message->user->name }}</span>
                        @endif
                        @if ($message->body)
                            <p>{{ $message->body }}</p>
                        @endif
                        @if ($message->hasAttachment())
                            <a href="{{ route('workspace.messages.attachments.download', [$workspace, $conversation, $message]) }}">
                                📎 {{ $message->attachment_name }}
                            </a>
                        @endif
                        <span class="msg-meta">{{ $message->created_at->format('d/m H:i') }}</span>
                    </div>
                </div>
            @empty
                <p class="muted">Aucun message. Dites bonjour !</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('workspace.messages.messages.store', [$workspace, $conversation]) }}"
              enctype="multipart/form-data" class="chat-form">
            @csrf
            <textarea name="body" rows="2" maxlength="5000"
                      placeholder="Votre message…"></textarea>
            <div class="chat-form-actions">
                <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.doc,.docx,.xls,.xlsx,.zip">
                <button type="submit" class="small">Envoyer</button>
            </div>
            @error('body')<p class="error">{{ $message }}</p>@enderror
            @error('attachment')<p class="error">{{ $message }}</p>@enderror
        </form>
    </div>
@endsection
