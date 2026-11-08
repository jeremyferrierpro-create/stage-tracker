<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Jeu de démonstration neutre (T-010) : uniquement des adresses
 *
 * @example.test et des noms génériques — aucune donnée réelle (COAB
 * ou autre), conformément à l'acceptation de la tâche.
 *
 * Idempotent : ré-exécuter le seeder ne duplique pas les données.
 * Aucune entrée d'audit n'est écrite ici : le seed n'est pas une action
 * utilisateur traçable.
 */
class DemoSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'mot-de-passe-demo-2026';

    public function run(): void
    {
        if (User::query()->where('email', 'admin@example.test')->exists()) {
            $this->command?->line('Jeu de démonstration déjà présent : seeding ignoré.');

            return;
        }

        $admin = $this->createUser('Admin Démo', 'admin@example.test');
        $trainee = $this->createUser('Stagiaire Démo', 'stagiaire@example.test');
        $supervisor = $this->createUser('Maître de Stage Démo', 'maitre-de-stage@example.test');
        $tutor = $this->createUser('Tuteur Démo', 'tuteur@example.test');

        $workspace = Workspace::create([
            'name' => 'Espace Démo',
            'type' => 'pedagogical',
            'owner_id' => $admin->id,
            'status' => 'active',
        ]);

        $this->member($workspace, $admin, 'owner');
        $this->member($workspace, $trainee, 'trainee');
        $this->member($workspace, $supervisor, 'supervisor');
        $this->member($workspace, $tutor, 'tutor');

        Organization::create([
            'workspace_id' => $workspace->id,
            'name' => 'Entreprise Démo',
            'type' => 'company',
            'contact_email' => 'contact@entreprise-demo.test',
            'contact_phone' => '+33 1 00 00 00 00',
            'address' => '1 Rue Fictive, 75000 Ville Imaginaire',
        ]);

        Organization::create([
            'workspace_id' => $workspace->id,
            'name' => 'Centre de Formation Démo',
            'type' => 'training_center',
            'contact_email' => 'contact@centre-formation-demo.test',
            'contact_phone' => '+33 1 00 00 00 01',
            'address' => '2 Allée Exemple, 75000 Ville Imaginaire',
        ]);

        $this->command?->line(
            'Jeu de démonstration créé : utilisateurs (mot de passe « '.self::DEMO_PASSWORD.' »),'
            .' espace « Espace Démo » et deux organisations neutres.'
        );
    }

    private function createUser(string $name, string $email): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password_hash' => self::DEMO_PASSWORD,
            'status' => 'active',
        ]);
    }

    private function member(Workspace $workspace, User $user, string $role): void
    {
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }
}
