<?php

namespace App\Http\Controllers;

use App\Domain\Identity\InvitationService;
use App\Domain\Identity\Models\Workspace;
use App\Http\Requests\Identity\StoreInvitationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Gestion des membres et des invitations d'un workspace (T-007).
 */
class MemberController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function index(Workspace $workspace): View
    {
        $this->authorize('manageMembers', $workspace);

        $members = $workspace->members()
            ->with('user')
            ->orderBy('role')
            ->orderBy('id')
            ->get();

        $pendingInvitations = $workspace->invitations()
            ->with('inviter')
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderBy('expires_at')
            ->get();

        return view('workspace.members', [
            'workspace' => $workspace,
            'members' => $members,
            'pendingInvitations' => $pendingInvitations,
            'assignableRoles' => $this->invitations->assignableRolesFor($workspace, request()->user()),
        ]);
    }

    public function store(StoreInvitationRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        $this->invitations->invite(
            $workspace,
            $request->user(),
            (string) $request->input('email'),
            (string) $request->input('role'),
        );

        return back()->with('status', 'Invitation envoyée.');
    }

    public function destroy(Workspace $workspace, string $invitation): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        $pending = $workspace->invitations()
            ->whereNull('accepted_at')
            ->findOrFail($invitation);

        $this->invitations->cancel($pending, request()->user());

        return back()->with('status', 'Invitation annulée.');
    }
}
