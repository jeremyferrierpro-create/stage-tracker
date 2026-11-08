<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateWorkspace extends Command
{
    protected $signature = 'app:create-workspace
                            {name : Nom de l\'espace de travail}
                            {--owner= : E-mail du propriétaire}
                            {--type=personal : personal|association|professional|pedagogical}';

    protected $description = 'Crée un espace de travail et son appartenance propriétaire';

    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));
        $type = (string) $this->option('type');

        if ($name === '' || Str::length($name) > 120) {
            $this->error('Le nom doit contenir entre 1 et 120 caractères.');

            return self::FAILURE;
        }

        if (! in_array($type, Workspace::TYPES, true)) {
            $this->error('Type invalide. Valeurs acceptées : '.implode(', ', Workspace::TYPES).'.');

            return self::FAILURE;
        }

        $email = Str::lower(trim((string) ($this->option('owner') ?: $this->ask('E-mail du propriétaire'))));
        $owner = User::where('email', $email)->first();

        if ($owner === null) {
            $this->error("Aucun compte pour {$email}. Créez-le avec : php artisan app:create-user {$email}");

            return self::FAILURE;
        }

        $workspace = DB::transaction(function () use ($name, $type, $owner): Workspace {
            $workspace = Workspace::create([
                'name' => $name,
                'type' => $type,
                'owner_id' => $owner->id,
                'status' => 'active',
            ]);

            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $owner->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            app(AuditLogger::class)->record(
                'workspace.created',
                $workspace,
                $workspace,
                [
                    'workspace_name' => $workspace->name,
                    'status' => $workspace->status,
                ],
                $owner,
            );

            return $workspace;
        });

        $this->info("Espace créé : {$workspace->name} ({$workspace->id}), propriétaire {$owner->email}.");

        return self::SUCCESS;
    }
}
