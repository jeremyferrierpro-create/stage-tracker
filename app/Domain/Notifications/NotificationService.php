<?php

namespace App\Domain\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Notifications\Models\Notification;
use Illuminate\Support\Collection;

/**
 * Émission et lecture des notifications in-app (REQ-F-002).
 * Le scope global `workspace` de BelongsToWorkspace peut être inactif
 * hors middleware (ex. acceptation d'invitation) : toutes les requêtes
 * filtrent explicitement par workspace_id.
 */
final class NotificationService
{
    /**
     * Crée une notification. Si `data.dedupe_key` est fourni, une
     * notification non lue identique n'est pas dupliquée.
     */
    public function send(
        Workspace $workspace,
        User $recipient,
        string $type,
        string $title,
        ?string $body = null,
        array $data = [],
    ): ?Notification {
        $dedupeKey = $data['dedupe_key'] ?? null;

        if ($dedupeKey !== null) {
            $exists = Notification::query()
                ->where('workspace_id', $workspace->id)
                ->where('user_id', $recipient->id)
                ->where('type', $type)
                ->where('data->dedupe_key', $dedupeKey)
                ->whereNull('read_at')
                ->exists();

            if ($exists) {
                return null;
            }
        }

        return Notification::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data === [] ? null : $data,
        ]);
    }

    /**
     * @return Collection<int, Notification>
     */
    public function latestFor(User $user, Workspace $workspace, int $limit = 100): Collection
    {
        return Notification::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    public function unreadCount(User $user, Workspace $workspace): int
    {
        return Notification::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @return Collection<int, Notification>
     */
    public function unreadFor(User $user, Workspace $workspace, int $limit = 5): Collection
    {
        return Notification::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->oldest('created_at')
            ->limit($limit)
            ->get();
    }

    public function markRead(Notification $notification, User $user): void
    {
        if ($notification->user_id !== $user->id) {
            abort(403);
        }

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }
    }

    public function markAllRead(User $user, Workspace $workspace): int
    {
        return Notification::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
