<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Models\Workspace;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Notifications in-app (REQ-F-002) : liste, marquage lu et ouverture
 * de la cible (`data.url`, liens internes uniquement).
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request, Workspace $workspace): View
    {
        return view('notifications.index', [
            'workspace' => $workspace,
            'notifications' => $this->notifications->latestFor($request->user(), $workspace),
        ]);
    }

    public function markRead(Request $request, Workspace $workspace, string $notification): RedirectResponse
    {
        $notification = Notification::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($notification);

        $this->notifications->markRead($notification, $request->user());

        return back();
    }

    public function markAllRead(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->notifications->markAllRead($request->user(), $workspace);

        return redirect()->route('workspace.notifications.index', $workspace);
    }

    /**
     * Ouvre la cible de la notification puis la marque lue. Le redirect
     * n'accepte que des chemins internes (pas d'URL externe / protocol-relative).
     */
    public function open(Request $request, Workspace $workspace, string $notification): RedirectResponse
    {
        $notification = Notification::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($notification);

        $this->notifications->markRead($notification, $request->user());

        $url = $notification->data['url'] ?? null;

        if (is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return redirect($url);
        }

        return redirect()->route('workspace.notifications.index', $workspace);
    }
}
