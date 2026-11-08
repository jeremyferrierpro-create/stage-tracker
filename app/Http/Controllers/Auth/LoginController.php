<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Protection contre la fixation de session.
        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->record(
            'auth.login',
            null,
            $user,
            ['status' => 'success'],
            $user,
        );

        $redirect = $request->query('redirect');
        $fallback = is_string($redirect)
            && Str::startsWith($redirect, '/')
            && ! Str::startsWith($redirect, '//')
                ? $redirect
                : route('workspaces.index');

        return redirect()->intended($fallback);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->audit->record('auth.logout', null, $user, [], $user);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
