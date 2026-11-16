<?php

namespace Database\Seeders;

use App\Domain\Evidence\Models\ActivityEvidence;
use App\Domain\Evidence\Models\Evidence;
use App\Domain\Framework\Models\Competency;
use App\Domain\Framework\Models\CompetencyBlock;
use App\Domain\Framework\Models\CompetencyCriterion;
use App\Domain\Framework\Models\CompetencyFramework;
use App\Domain\Framework\Models\FrameworkVersion;
use App\Domain\Git\Models\ActivityCommit;
use App\Domain\Git\Models\Commit;
use App\Domain\Git\Models\Repository;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Journal\Models\DailyLog;
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

        $mockupActivity = Activity::create([
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

        $sharedEvidence = Evidence::create([
            'workspace_id' => $workspace->id,
            'owner_id' => $trainee->id,
            'title' => 'Maquette desktop de la page d\'accueil',
            'type' => 'mockup',
            'description' => 'Maquette Figma de la page d\'accueil (desktop).',
            'demonstrated' => 'L\'ergonomie de la page d\'accueil a été validée avec le maître de stage.',
            'external_url' => 'https://www.figma.com/file/demo-maquette-accueil',
            'visibility' => 'shared',
        ]);

        $privateEvidence = Evidence::create([
            'workspace_id' => $workspace->id,
            'owner_id' => $trainee->id,
            'title' => 'Notes personnelles de préparation',
            'type' => 'technical_doc',
            'description' => 'Notes privées du stagiaire (non visibles des autres participants).',
            'demonstrated' => 'Démarche personnelle de préparation de l\'entretien client.',
            'external_url' => 'https://example.test/notes-privees',
            'visibility' => 'private',
        ]);

        ActivityEvidence::create([
            'activity_id' => $mockupActivity->id,
            'evidence_id' => $sharedEvidence->id,
            'workspace_id' => $workspace->id,
            'note' => 'Documente la maquette validée.',
        ]);

        ActivityEvidence::create([
            'activity_id' => $mockupActivity->id,
            'evidence_id' => $privateEvidence->id,
            'workspace_id' => $workspace->id,
            'note' => null,
        ]);

        DailyLog::create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'author_id' => $trainee->id,
            'work_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'duration_minutes' => 480,
            'objectives' => 'Avancer sur la maquette puis commencer l\'intégration.',
            'work_done' => 'Finalisation de la maquette desktop et début de l\'intégration HTML/CSS responsive.',
            'difficulties' => 'Choix des points de rupture responsive.',
            'solutions' => 'Validation des breakpoints avec le maître de stage.',
            'results' => 'Maquette validée, intégration démarrée.',
            'remaining' => 'Terminer l\'intégration des sections.',
            'private_notes' => 'Notes personnelles du stagiaire (journal privé).',
            'status' => 'closed',
        ]);

        $demoRepository = Repository::create([
            'project_id' => $project->id,
            'workspace_id' => $workspace->id,
            'provider' => 'github',
            'external_id' => 'demo/stage-tracker-vitrine',
            'name' => 'Dépôt Démo — Site vitrine',
            'repository_url' => 'https://github.com/demo/stage-tracker-vitrine',
            'default_branch' => 'main',
            'token_encrypted' => null,
            'last_synced_at' => now(),
        ]);

        $demoCommits = [];
        foreach ([
            ['sha' => str_repeat('a', 40), 'message' => 'feat: maquette de la page d\'accueil', 'days' => 12],
            ['sha' => str_repeat('b', 40), 'message' => 'feat: intégration HTML/CSS responsive', 'days' => 6],
            ['sha' => str_repeat('c', 40), 'message' => 'fix: correctif d\'accessibilité des liens', 'days' => 2],
        ] as $entry) {
            $demoCommits[] = Commit::create([
                'repository_id' => $demoRepository->id,
                'workspace_id' => $workspace->id,
                'sha' => $entry['sha'],
                'author_name' => 'Stagiaire Démo',
                'author_email' => 'stagiaire@example.test',
                'committed_at' => now()->startOfDay()->subDays($entry['days'])->setTime(14, 30),
                'message' => $entry['message'],
                'branch' => 'main',
                'url' => 'https://github.com/demo/stage-tracker-vitrine/commit/'.$entry['sha'],
                'imported_at' => now(),
            ]);
        }

        ActivityCommit::create([
            'activity_id' => $mockupActivity->id,
            'commit_id' => $demoCommits[0]->id,
            'workspace_id' => $workspace->id,
            'linked_by' => $trainee->id,
            'linked_at' => now(),
        ]);

        $this->command?->line(
            'Jeu de démonstration créé : utilisateurs (mot de passe « '.self::DEMO_PASSWORD.' »),'
            .' espace « Espace Démo », formations, référentiel publié, stage, projet démo,'
            .' deux preuves (une partagée, une privée), une entrée de journal'
            .' et dépôt Git démo avec 3 commits.'
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
