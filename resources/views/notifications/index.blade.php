@extends('layouts.app')

@section('title', 'Notifications')

@section('breadcrumb')
    <x-breadcrumb :items="[['label' => 'Notifications']]" />
@endsection

@section('content')
    <div class="page-head">
        <h1>Notifications</h1>
        <form method="POST" action="{{ route('workspace.notifications.read_all', $workspace) }}">
            @csrf
            <button type="submit" class="button secondary small">Tout marquer comme lu</button>
        </form>
    </div>

    <div class="card">
        @forelse ($notifications as $notification)
            <div class="notification-item {{ $notification->isRead() ? 'is-read' : 'is-unread' }}">
                <div>
                    <strong>{{ $notification->title }}</strong>
                    @if ($notification->body)
                        <p class="muted">{{ $notification->body }}</p>
                    @endif
                    <p class="muted small">{{ $notification->created_at->diffForHumans() }}</p>
                </div>
                <div class="notification-actions">
                    @if (! $notification->isRead())
                        <form method="POST" action="{{ route('workspace.notifications.read', [$workspace, $notification]) }}">
                            @csrf
                            <button type="submit" class="link small">Marquer lu</button>
                        </form>
                    @endif
                    @if (! empty($notification->data['url']))
                        <a class="button secondary small"
                           href="{{ route('workspace.notifications.open', [$workspace, $notification]) }}">Ouvrir</a>
                    @endif
                </div>
            </div>
        @empty
            <p class="muted">Aucune notification.</p>
        @endforelse
    </div>
@endsection
