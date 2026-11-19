<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\AuditLogger;
use App\Domain\Evaluation\Models\Competency;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Internship\Models\InternshipParticipant;
use App\Domain\Reporting\Models\ReportTemplate;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ], [
            'name.required' => 'Le nom complet est obligatoire.',
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.email' => 'L\'adresse e-mail n\'est pas valide.',
            'email.unique' => 'Un compte existe déjà avec cette adresse e-mail.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 12 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => trim((string) $validated['name']),
                'email' => Str::lower(trim((string) $validated['email'])),
                'password_hash' => $validated['password'],
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            // 1. Création de l'espace de stage dédié et étanche
            $workspace = Workspace::create([
                'name' => 'Mon Stage DWWM — '.$user->name,
                'type' => 'pedagogical',
                'owner_id' => $user->id,
                'status' => 'active',
            ]);

            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            // 2. Création du stage actif rattaché au stagiaire
            $internship = Internship::create([
                'workspace_id' => $workspace->id,
                'trainee_id' => $user->id,
                'title' => 'Stage DWWM — '.$user->name,
                'company_name' => 'Mon entreprise d\'accueil',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonths(2)->toDateString(),
                'objectives' => 'Préparation des épreuves du Titre Professionnel Développeur Web et Web Mobile (CCP 1 et CCP 2).',
                'status' => 'active',
            ]);

            InternshipParticipant::create([
                'internship_id' => $internship->id,
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => 'trainee',
                'status' => 'active',
            ]);

            // 3. Initialisation automatique des 6 compétences officielles DWWM (2023)
            $competencies = [
                [
                    'code' => 'C1',
                    'title' => 'Maquetter des interfaces utilisateur web ou web mobile',
                    'description' => 'CCP 1 : Développer la partie front-end sécurisée — Personas, wireframes, maquettes Figma/prototype, ergonomie et charte graphique.',
                ],
                [
                    'code' => 'C2',
                    'title' => 'Réaliser des interfaces utilisateur statiques web ou web mobile',
                    'description' => 'CCP 1 : Développer la partie front-end sécurisée — Intégration HTML5 sémantique, CSS3 (Flexbox/Grid), conformité accessibilité RGAA, éco-conception.',
                ],
                [
                    'code' => 'C3',
                    'title' => 'Développer la partie dynamique des interfaces utilisateur web ou web mobile',
                    'description' => 'CCP 1 : Développer la partie front-end sécurisée — JavaScript / TypeScript, manipulation du DOM, requêtes asynchrones, validation de formulaires.',
                ],
                [
                    'code' => 'C4',
                    'title' => 'Mettre en place une base de données relationnelle',
                    'description' => 'CCP 2 : Développer la partie back-end sécurisée — Dictionnaire de données, MCD/MLD, scripts SQL DDL (tables, clés primaires/étrangères, index, triggers).',
                ],
                [
                    'code' => 'C5',
                    'title' => 'Développer des composants d’accès aux données SQL et NoSQL',
                    'description' => 'CCP 2 : Développer la partie back-end sécurisée — Modèles ORM/Eloquent, requêtes préparées, migrations, gestion des transactions, protection contre injections SQL.',
                ],
                [
                    'code' => 'C6',
                    'title' => 'Développer des composants métier côté serveur',
                    'description' => 'CCP 2 : Développer la partie back-end sécurisée — Contrôleurs, routes, validation serveur, authentification sécurisée, hashage mots de passe, conformité OWASP Top 10.',
                ],
            ];

            foreach ($competencies as $comp) {
                Competency::create([
                    'workspace_id' => $workspace->id,
                    'code' => $comp['code'],
                    'title' => $comp['title'],
                    'description' => $comp['description'],
                ]);
            }

            // 4. Initialisation de la trame de rapport officielle DWWM
            $template = ReportTemplate::create([
                'workspace_id' => $workspace->id,
                'name' => 'Synthèse de stage DWWM',
                'type' => 'memoir',
                'description' => 'Trame officielle pour le mémoire, le dossier professionnel et la soutenance orale DWWM.',
                'status' => 'published',
            ]);

            $template->versions()->create([
                'version' => 'v1.0.0',
                'schema_json' => [
                    'title' => 'Synthèse de stage DWWM',
                    'sections' => ['identity', 'activities', 'daily_logs', 'evidences', 'competencies', 'tasks', 'commits', 'collaboration', 'synthesis'],
                ],
                'published_at' => now(),
            ]);

            return $user;
        });

        $this->audit->record(
            'auth.register',
            null,
            $user,
            ['status' => 'success'],
            $user,
        );

        Auth::login($user);
        $request->session()->regenerate();

        $workspace = $user->workspaces()->firstOrFail();

        return redirect()
            ->route('workspace.dashboard', $workspace)
            ->with('status', 'Bienvenue ! Votre espace de stage DWWM est prêt avec les 6 compétences officielles.');
    }
}
