<?php

namespace App\Http\Requests\Auth;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 900;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Authentifie l'utilisateur ou renvoie une erreur neutre.
     *
     * @throws ValidationException
     * @throws ThrottleRequestsException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'email' => Str::lower(trim((string) $this->input('email'))),
            'password' => (string) $this->input('password'),
            'status' => 'active',
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

            $attemptedUser = User::where('email', $credentials['email'])->first();

            app(AuditLogger::class)->record(
                'auth.login.failed',
                null,
                $attemptedUser,
                [
                    'email_hash' => hash('sha256', $credentials['email']),
                    'reason' => 'invalid_credentials',
                ],
                $attemptedUser,
            );

            // Message identique pour mot de passe faux, compte inconnu ou désactivé.
            throw ValidationException::withMessages([
                'email' => 'Identifiants incorrects ou compte désactivé.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * AC-1.2 : après 5 échecs en 15 minutes, réponse 429 avec Retry-After.
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        $attemptedUser = User::where('email', Str::lower((string) $this->input('email')))->first();

        app(AuditLogger::class)->record(
            'auth.login.throttled',
            null,
            $attemptedUser,
            [
                'email_hash' => hash('sha256', Str::lower((string) $this->input('email'))),
                'reason' => 'throttled',
            ],
            $attemptedUser,
        );

        throw new ThrottleRequestsException(
            'Trop de tentatives de connexion. Réessayez dans '.$seconds.' secondes.',
            null,
            ['Retry-After' => (string) $seconds],
        );
    }

    private function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower((string) $this->input('email')).'|'.$this->ip()
        );
    }
}
