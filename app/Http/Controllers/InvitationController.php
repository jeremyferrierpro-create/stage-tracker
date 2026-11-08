<?php

namespace App\Http\Controllers;

use App\Domain\Identity\InvitationService;
use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\User;
use App\Http\Requests\Identity\RegisterViaInvitationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Parcours public d'une invitation (T-007, APPFLOW §6.1).
 */
class InvitationController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function show(string $token): View
    {
        $invitation = $this->findInvitation($token);
        $this->ensureUsable($invitation);

        return view('invitations.show', [
            'invitation' => $invitation,
            'token' => $token,
            'workspace' => $invitation->workspace,
            'inviter' => $invitation->inviter,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findInvitation($token);
        $this->ensureUsable($invitation);

        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless(
            Str::lower((string) $user->email) === Str::lower($invitation->email),
            403,
            'Cette invitation ne correspond pas à votre adresse e-mail.',
        );

        $this->invitations->accept($invitation, $user);

        return redirect()
            ->route('workspace.dashboard', $invitation->workspace_id)
            ->with('status', 'Invitation acceptée. Bienvenue dans l\'espace !');
    }

    public function decline(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findInvitation($token);
        $this->ensureUsable($invitation);

        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless(
            Str::lower((string) $user->email) === Str::lower($invitation->email),
            403,
            'Cette invitation ne correspond pas à votre adresse e-mail.',
        );

        $this->invitations->decline($invitation, $user);

        return redirect()
            ->route('home')
            ->with('status', 'Invitation refusée.');
    }

    public function register(RegisterViaInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = $this->findInvitation($token);
        $this->ensureUsable($invitation);

        if (User::where('email', $invitation->email)->exists()) {
            return redirect()
                ->route('login', ['redirect' => route('invitations.show', $token)])
                ->withErrors([
                    'email' => 'Un compte existe déjà pour cette adresse. Connectez-vous pour accepter l\'invitation.',
                ]);
        }

        $user = DB::transaction(function () use ($request, $invitation): User {
            $user = new User([
                'name' => trim((string) $request->input('name')),
                'email' => $invitation->email,
                'password_hash' => (string) $request->input('password'),
                'status' => 'active',
            ]);
            // L'invitation prouve la maîtrise de l'adresse e-mail.
            $user->email_verified_at = now();
            $user->save();

            $this->invitations->accept($invitation, $user);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('workspace.dashboard', $invitation->workspace_id)
            ->with('status', 'Compte créé et invitation acceptée. Bienvenue !');
    }

    private function findInvitation(string $token): Invitation
    {
        $invitation = Invitation::where('token_hash', Invitation::hashToken($token))->first();

        abort_if($invitation === null, 404);

        return $invitation;
    }

    private function ensureUsable(Invitation $invitation): void
    {
        abort_if($invitation->isAccepted(), 410, 'Cette invitation a déjà été utilisée.');
        abort_if($invitation->isExpired(), 410, 'Cette invitation a expiré.');

        $workspace = $invitation->workspace;

        abort_if(
            $workspace === null || ! $workspace->isActive(),
            410,
            'Cet espace de travail n\'accepte plus d\'invitations.',
        );
    }
}
