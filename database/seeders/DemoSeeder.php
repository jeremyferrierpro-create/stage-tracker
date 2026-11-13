<?php

namespace Database\Seeders;

use App\Domain\Framework\Models\Competency;
use App\Domain\Framework\Models\CompetencyBlock;
use App\Domain\Framework\Models\CompetencyCriterion;
use App\Domain\Framework\Models\CompetencyFramework;
use App\Domain\Framework\Models\FrameworkVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Organization\Models\Organization;
use App\Domain\Training\Models\TrainingEnrollment;
use App\Domain\Training\Models\TrainingProgram;
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

        $program = TrainingProgram::create([
            'workspace_id' => $workspace->id,
            'organization_id' => Organization::query()
                ->where('workspace_id', $workspace->id)
                ->where('type', 'training_center')
                ->value('id'),
            'name' => 'Formation Démo (DWWM)',
            'certification' => 'DWWM — Développeur Web et Web Mobile',
            'start_date' => now()->startOfDay()->subMonth(),
            'end_date' => now()->startOfDay()->addMonths(8),
        ]);

        TrainingEnrollment::create([
            'user_id' => $trainee->id,
            'training_program_id' => $program->id,
            'workspace_id' => $workspace->id,
            'start_date' => now()->startOfDay()->subMonth(),
            'end_date' => now()->startOfDay()->addMonths(8),
            'status' => 'active',
        ]);

        $framework = CompetencyFramework::create([
            'name' => 'Référentiel Démo (pilote)',
            'issuing_authority' => 'Centre de Formation Démo',
            'description' => 'Référentiel fictif de démonstration (aucune donnée réelle).',
            'status' => 'draft',
        ]);

        $version = FrameworkVersion::create([
            'framework_id' => $framework->id,
            'version' => 'v1.0.0',
            'effective_date' => now()->startOfDay(),
            'status' => 'draft',
        ]);

        $blockCommunication = CompetencyBlock::create([
            'framework_version_id' => $version->id,
            'code' => 'B1',
            'title' => 'Communication professionnelle',
            'position' => 1,
        ]);

        $competency = Competency::create([
            'framework_version_id' => $version->id,
            'block_id' => $blockCommunication->id,
            'code' => 'C1.1',
            'title' => 'Rédiger un message professionnel',
            'position' => 1,
        ]);

        CompetencyCriterion::create([
            'competency_id' => $competency->id,
            'label' => 'Le message est clair et structuré.',
            'position' => 1,
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
