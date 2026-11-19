<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Changement de mot de passe en autonomie (Lot 3). Le mot de passe courant
 * est vérifié, le nouveau est haché par le cast `hashed` du modèle User.
 */
class AccountPasswordController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit(): View
    {
        return view('account.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'current_password' => 'Le mot de passe actuel est incorrect.',
            ]);
        }

        $user->forceFill(['password_hash' => $validated['password']])->save();

        $this->audit->record(
            'user.password_changed',
            null,
            $user,
            ['subject' => 'account'],
            $user,
        );

        return redirect()
            ->route('account.password.edit')
            ->with('status', 'Mot de passe modifié.');
    }
}
