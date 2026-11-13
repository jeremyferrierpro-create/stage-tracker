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
use App\Domain\Internship\Models\Internship;
use App\Domain\Internship\Models\InternshipParticipant;
use App\Domain\Organization\Models\Organization;
use App\Domain\Project\Models\Activity;
use App\Domain\Project\Models\Project;
use App\Domain\Training\Models\TrainingEnrollment;
use App\Domain\Training\Models\TrainingProgram;
use Illuminate\Database\Seeder;

/**
 * Jeu de démonstration neutre (T-010, étendu T-020/T-023/T-025).
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

        $company = Organization::create([
            'workspace_id' => $workspace->id,
            'name' => 'Entreprise Démo',
            'type' => 'company',
            'contact_email' => 'contact@entreprise-demo.test',
            'contact_phone' => '+33 1 00 00 00 00',
            'address' => '1 Rue Fictive, 75000 Ville Imaginaire',
        ]);

        $trainingCenter = Organization::create([
            'workspace_id' => $workspace->id,
            'name' => 'Centre de Formation Démo',
            'type' => 'training_center',
            'contact_email' => 'contact@centre-formation-demo.test',
            'contact_phone' => '+33 1 00 00 00 01',
            'address' => '2 Allée Exemple, 75000 Ville Imaginaire',
        ]);

        $program = TrainingProgram::create([
            'workspace_id' => $workspace->id,
            'organization_id' => $trainingCenter->id,
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

        $block = CompetencyBlock::create([
            'framework_version_id' => $version->id,
            'code' => 'B1',
            'title' => 'Communication professionnelle',
            'position' => 1,
        ]);

        $competency = Competency::create([
            'framework_version_id' => $version->id,
            'block_id' => $block->id,
            'code' => 'C1.1',
            'title' => 'Rédiger un message professionnel',
            'position' => 1,
        ]);

        CompetencyCriterion::create([
            'competency_id' => $competency->id,
            'label' => 'Le message est clair et structuré.',
            'position' => 1,
        ]);

        $version->forceFill([
            'status' => 'published',
            'published_at' => now(),
        ])->save();

        $internship = Internship::create([
            'workspace_id' => $workspace->id,
            'trainee_id' => $trainee->id,
            'host_organization_id' => $company->id,
            'training_program_id' => $program->id,
            'framework_version_id' => $version->id,
            'title' => 'Stage Démo — Développeur Web',
            'start_date' => now()->startOfDay()->subMonth(),
            'end_date' => now()->startOfDay()->addMonths(4),
            'objectives' => 'Découvrir le métier et produire des livrables concrets.',
            'status' => 'active',
        ]);

        InternshipParticipant::create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'user_id' => $supervisor->id,
            'role' => 'supervisor',
            'permissions' => [],
            'status' => 'active',
        ]);

        $project = Project::create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'name' => 'Projet Démo — Site vitrine',
            'description' => 'Réalisation d\'un site vitrine pour un client fictif.',
            'status' => 'active',
            'start_date' => now()->startOfDay()->subMonth(),
            'end_date' => now()->startOfDay()->addMonths(2),
        ]);

        Activity::create([
            'project_id' => $project->id,
            'workspace_id' => $workspace->id,
            'title' => 'Maquette de la page d\'accueil',
            'description' => 'Conception de la maquette desktop et mobile.',
            'type' => 'design',
            'status' => 'done',
            'priority' => 'high',
            'objectives' => 'Valider l\'ergonomie avec le maître de stage.',
            'results' => 'Maquette validée.',
        ]);

        Activity::create([
            'project_id' => $project->id,
            'workspace_id' => $workspace->id,
            'title' => 'Intégration HTML/CSS',
            'description' => 'Intégration responsive de la maquette.',
            'type' => 'feature',
            'status' => 'in_progress',
            'priority' => 'medium',
            'objectives' => 'Respecter la maquette validée.',
        ]);

        $this->command?->line(
            'Jeu de démonstration créé : utilisateurs (mot de passe « '.self::DEMO_PASSWORD.' »),'
            .' espace « Espace Démo », formations, référentiel publié, stage et projet démo.'
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
