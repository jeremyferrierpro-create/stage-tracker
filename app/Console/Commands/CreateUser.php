<?php

namespace App\Console\Commands;

use App\Domain\Identity\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateUser extends Command
{
    protected $signature = 'app:create-user
                            {email : Adresse e-mail du compte}
                            {--name= : Nom complet}
                            {--password= : Mot de passe (déconseillé : visible dans l\'historique)}';

    protected $description = 'Crée un compte utilisateur actif (aucune inscription publique en V1)';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Adresse e-mail invalide.');

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("Un compte existe déjà pour {$email}.");

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?: $this->ask('Nom complet'));
        $name = trim($name);

        if ($name === '' || Str::length($name) > 120) {
            $this->error('Le nom doit contenir entre 1 et 120 caractères.');

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: $this->secret('Mot de passe (12 caractères minimum)'));

        if (Str::length($password) < 12) {
            $this->error('Le mot de passe doit contenir au moins 12 caractères.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password_hash' => $password,
            'status' => 'active',
        ]);

        $this->info("Compte créé : {$user->email} (id {$user->id}).");

        return self::SUCCESS;
    }
}
