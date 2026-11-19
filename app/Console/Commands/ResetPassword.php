<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Réinitialisation administrateur (Lot 3). Le mot de passe généré est affiché
 * une seule fois dans le terminal ; il n'est jamais journalisé.
 */
class ResetPassword extends Command
{
    protected $signature = 'app:reset-password
                            {email : Adresse e-mail du compte}
                            {--password= : Mot de passe imposé (déconseillé)}';

    protected $description = 'Réinitialise le mot de passe d\'un compte et l\'audite';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("Aucun compte pour {$email}.");

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: Str::password(16));

        if (Str::length($password) < 12) {
            $this->error('Le mot de passe doit contenir au moins 12 caractères.');

            return self::FAILURE;
        }

        $user->forceFill(['password_hash' => $password])->save();

        app(AuditLogger::class)->record(
            'user.password_reset',
            null,
            $user,
            [
                'email_hash' => hash('sha256', $user->email),
                'reason' => 'admin',
            ],
        );

        $this->info("Mot de passe réinitialisé pour {$user->email}.");

        if (! $this->option('password')) {
            $this->warn('Mot de passe temporaire (à transmettre par un canal sûr) : '.$password);
        }

        return self::SUCCESS;
    }
}
