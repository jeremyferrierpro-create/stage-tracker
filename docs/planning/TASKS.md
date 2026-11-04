# TASKS — Découpage atomique de l'implémentation

> Chaque tâche est une unité fonctionnelle vérifiable. Une tâche ne démarre que
> si toutes ses dépendances sont **PASS** avec preuve. Format : objectif,
> entrées, traitement, sorties, fichiers, tests, preuves, acceptation, risques.

## Phase 1 — Socle multiutilisateur

### T-001 — Squelette Laravel et structure de domaine
- **Objectif** : créer l'application Laravel 13.40.0 dans le dépôt existant.
- **Dépend de** : —
- **Entrées** : `composer.json` cible, PHP 8.5.12, décision DEC-ST-001.
- **Traitement** : `composer create-project` dans un répertoire temporaire puis fusion contrôlée ; création de `app/Domain/*`, `app/Integrations/*`, `app/Policies`.
- **Sorties** : application bootable (`php artisan --version`), arborescence de domaines.
- **Fichiers** : `composer.json`, `composer.lock`, `app/Domain/**`, `bootstrap/app.php`.
- **Tests** : `php artisan --version` ; `php artisan about`.
- **Preuves** : sortie réelle des commandes + hash du `composer.lock`.
- **Acceptation** : l'application démarre, aucune erreur, structure conforme à TRD §2.
- **Risques** : conflit avec les fichiers existants (README, .env.example) → fusion manuelle, aucune écrasement silencieux.

### T-002 — Environnement et PostgreSQL Docker
- **Objectif** : connecter l'application à PostgreSQL 16 local.
- **Dépend de** : T-001.
- **Entrées** : `docker-compose.yml`, `.env.example`, DEC-ST-009.
- **Traitement** : `docker compose up -d` ; création `.env` local ; configuration `pgsql` ; `php artisan migrate` sur une migration initiale.
- **Sorties** : conteneur healthy, connexion validée.
- **Fichiers** : `.env` (non versionné), `config/database.php`, `docker-compose.yml`.
- **Tests** : `docker compose ps` (healthy) ; `php artisan db:show`.
- **Preuves** : sortie des deux commandes.
- **Acceptation** : connexion PostgreSQL réelle, migration exécutée, rollback vérifié.
- **Risques** : port 5432 occupé ; mot de passe manquant → échec explicite.

### T-003 — Chaîne qualité et base de test
- **Objectif** : garantir formatage et tests reproductibles.
- **Dépend de** : T-001, T-002.
- **Entrées** : PHPUnit, Pint, `.env.testing`.
- **Traitement** : configuration de `phpunit.xml` (base `stage_tracker_test`), script `composer test`, Pint en CI locale.
- **Sorties** : suite vide verte, base de test isolée.
- **Fichiers** : `phpunit.xml`, `composer.json`, `.env.testing`, `pint.json`.
- **Tests** : `vendor/bin/pint --test` ; `php artisan test`.
- **Preuves** : sorties avec code 0.
- **Acceptation** : aucun test n'utilise la base pilote.
- **Risques** : base de test partagée → risque de pollution ; isolation par transaction.

### T-004 — Authentification
- **Objectif** : connexion/déconnexion sécurisées.
- **Dépend de** : T-002, T-003.
- **Entrées** : `users` (MLD §2), SECURITY §3.1.
- **Traitement** : migration users, modèle, login/logout, hachage Argon2id, rate limiting 5/15 min, sessions en base.
- **Sorties** : écrans connexion, sessions, audit de connexion.
- **Fichiers** : `app/Domain/Identity/Models/User.php`, `app/Http/Controllers/Auth/*`, `routes/web.php`, `resources/views/auth/*`.
- **Tests** : connexion valide/invalide, 429 après 5 échecs, session régénérée.
- **Preuves** : tests Feature exécutés + sortie.
- **Acceptation** : AC-1.1, AC-1.2, AC-1.3.
- **Risques** : fuite d'information dans les messages → messages neutres.

### T-005 — Workspaces, appartenances et scope
- **Objectif** : poser l'isolation multi-tenant.
- **Dépend de** : T-004.
- **Entrées** : `workspaces`, `workspace_members`, ADR-002.
- **Traitement** : migrations, modèles, `WorkspaceScope`, middleware `EnsureWorkspaceMember`, résolution `/w/{workspace}`.
- **Sorties** : toute requête métier filtrée ; accès non-membre refusé.
- **Fichiers** : `app/Domain/Identity/Models/{Workspace,WorkspaceMember}.php`, `app/Models/Concerns/BelongsToWorkspace.php`, `app/Http/Middleware/EnsureWorkspaceMember.php`.
- **Tests** : MT-01, MT-02 (premières versions).
- **Preuves** : tests + audit des refus.
- **Acceptation** : aucune fuite inter-workspace sur les ressources de base.
- **Risques** : oubli de scope sur un nouveau modèle → test générique de couverture.

### T-006 — Policies de base
- **Objectif** : autoriser par rôle contextualisé.
- **Dépend de** : T-005.
- **Entrées** : matrice rôles/permissions.
- **Traitement** : policies Workspace/User ; Gates ; tests d'autorisation.
- **Sorties** : 403 cohérents, aucune fuite d'existence.
- **Fichiers** : `app/Policies/*`, `app/Providers/AuthServiceProvider.php`.
- **Tests** : MT-02, MT-03.
- **Preuves** : sorties de tests.
- **Acceptation** : matrice appliquée pour les actions de base.
- **Risques** : policy trop permissive → revue croisée.

### T-007 — Invitations
- **Objectif** : inviter un utilisateur dans un workspace.
- **Dépend de** : T-005, T-006.
- **Entrées** : `invitations` (MLD), APPFLOW §6.1.
- **Traitement** : création, token hashé, expiration 7 j, acceptation, refus.
- **Sorties** : flux complet d'invitation.
- **Fichiers** : `app/Domain/Identity/*`, `routes/web.php`, mails.
- **Tests** : invitation valide, expirée, token invalide, double acceptation.
- **Preuves** : tests + audit.
- **Acceptation** : un invité ne peut pas dépasser le rôle proposé.
- **Risques** : énumération d'e-mails → réponse neutre.

### T-008 — Organisations
- **Objectif** : gérer les organisations d'accueil et organismes.
- **Dépend de** : T-005.
- **Entrées** : `organizations`, `organization_members`.
- **Traitement** : CRUD, distinction des types, archivage.
- **Sorties** : écrans et API organisations.
- **Fichiers** : `app/Domain/Organization/*`, vues.
- **Tests** : AC-3.1, isolation.
- **Preuves** : tests.
- **Acceptation** : organisation d'accueil ≠ organisme de formation.
- **Risques** : confusion des types → libellés explicites.

### T-009 — Journal d'audit
- **Objectif** : tracer les actions sensibles.
- **Dépend de** : T-004, T-005.
- **Entrées** : `audit_logs` (MLD), SECURITY §3.6.
- **Traitement** : service d'audit append-only, hooks sur connexion, invitation, partage, export.
- **Sorties** : journal consultable par admin.
- **Fichiers** : `app/Domain/Audit/*`, migration.
- **Tests** : chaque action sensible crée une entrée ; aucune mise à jour possible.
- **Preuves** : tests + extrait d'audit.
- **Acceptation** : AC-T.3.
- **Risques** : données sensibles dans `metadata` → liste blanche.

### T-010 — Factories et seeders
- **Objectif** : données de test reproductibles, sans données réelles.
- **Dépend de** : T-005, T-008.
- **Entrées** : MLD.
- **Traitement** : factories par entité ; seeders `local`/`test` uniquement.
- **Sorties** : jeu de données de démonstration neutre.
- **Fichiers** : `database/factories/*`, `database/seeders/*`.
- **Tests** : `migrate:fresh --seed` en environnement test.
- **Preuves** : sortie.
- **Acceptation** : aucune donnée COAB dans les seeders.
- **Risques** : seeders exécutés en production → garde d'environnement.

### T-011 — Layout et navigation
- **Objectif** : fournir l'ossature UI.
- **Dépend de** : T-004.
- **Entrées** : UI-UX §2.
- **Traitement** : layout Blade, navigation latérale, sélecteur de workspace, fil d'Ariane.
- **Sorties** : pages vides navigables.
- **Fichiers** : `resources/views/layouts/*`, `resources/views/components/*`, CSS.
- **Tests** : rendu, navigation clavier de base.
- **Preuves** : captures + test de rendu.
- **Acceptation** : navigation cohérente, responsive.
- **Risques** : dette d'accessibilité → audit précoce.

### T-012 — Campagne de tests phase 1
- **Objectif** : prouver l'isolation et les rôles.
- **Dépend de** : T-004 → T-011.
- **Entrées** : MT-03, MT-10.
- **Traitement** : exécution complète, correction des échecs.
- **Sorties** : rapport de campagne.
- **Fichiers** : `tests/Feature/Tenant/*`.
- **Tests** : MT-01, MT-02, MT-03, MT-10.
- **Preuves** : sortie console + evidence AIDF.
- **Acceptation** : 4 tests PASS.
- **Risques** : tests fragiles → factories stables.

## Phase 2 — Stages et suivi

### T-020 — Programmes de formation et inscriptions
- **Objectif** : gérer formations et inscriptions.
- **Dépend de** : T-012.
- **Entrées** : `training_programs`, `training_enrollments`.
- **Traitement** : CRUD, statuts, contraintes.
- **Sorties** : écrans admin.
- **Fichiers** : `app/Domain/Training/*`.
- **Tests** : création, unicité inscription, isolation.
- **Preuves** : tests.
- **Acceptation** : une inscription lie un utilisateur à un programme.
- **Risques** : doublons → contrainte unique.

### T-021 — Référentiels versionnés
- **Objectif** : modéliser frameworks, versions, blocs, compétences, critères.
- **Dépend de** : T-012.
- **Entrées** : MLD §5.
- **Traitement** : migrations, modèles, CRUD admin, hiérarchie.
- **Sorties** : saisie d'un référentiel complet.
- **Fichiers** : `app/Domain/Framework/*`.
- **Tests** : unicité code/version, hiérarchie, isolation.
- **Preuves** : tests + jeu de données DWWM fictif de test.
- **Acceptation** : aucun code spécifique à un référentiel.
- **Risques** : modèle trop rigide → revue avec un second référentiel de test.

### T-022 — Publication immuable
- **Objectif** : rendre une version publiée immuable.
- **Dépend de** : T-021.
- **Entrées** : INV-08, SECURITY.
- **Traitement** : statut `published`, trigger PostgreSQL, policy, tests.
- **Sorties** : refus de toute écriture sur version publiée.
- **Fichiers** : migration trigger, policy, service de publication.
- **Tests** : MT-05.
- **Preuves** : sortie de test + erreur PostgreSQL observée.
- **Acceptation** : MT-05 PASS.
- **Risques** : trigger trop large → tests de non-régression.

### T-023 — Stages et participants
- **Objectif** : créer et gérer un stage.
- **Dépend de** : T-020, T-022, T-008.
- **Entrées** : `internships`, `internship_participants`.
- **Traitement** : CRUD, FK composites, statuts, invitation des participants.
- **Sorties** : stage complet avec participants.
- **Fichiers** : `app/Domain/Internship/*`.
- **Tests** : AC-5.1, AC-5.2, MT-04.
- **Preuves** : tests.
- **Acceptation** : stage lié à une version publiée uniquement.
- **Risques** : dates incohérentes → validation serveur.

### T-024 — Projets
- **Objectif** : rattacher des projets à un stage.
- **Dépend de** : T-023.
- **Entrées** : `projects`.
- **Traitement** : CRUD, statuts, isolation.
- **Sorties** : écrans projet.
- **Fichiers** : `app/Domain/Project/*`.
- **Tests** : rattachement, isolation.
- **Preuves** : tests.
- **Acceptation** : un projet appartient à un seul stage.
- **Risques** : confusion projet/activité → libellés UI.

### T-025 — Activités
- **Objectif** : documenter les activités techniques.
- **Dépend de** : T-024.
- **Entrées** : `activities`.
- **Traitement** : CRUD, types, statuts, priorités, objectifs/résultats/difficultés/solutions.
- **Sorties** : fiches activité complètes.
- **Fichiers** : `app/Domain/Project/Models/Activity.php`, vues.
- **Tests** : AC-6.1, AC-6.2.
- **Preuves** : tests.
- **Acceptation** : activité sans commit valide.
- **Risques** : champs libres trop nombreux → aide contextuelle.

### T-026 — Tâches et Kanban
- **Objectif** : fournir le Kanban source de vérité.
- **Dépend de** : T-025.
- **Entrées** : `tasks`, UI-UX §3.
- **Traitement** : CRUD, colonnes, glisser-déposer Livewire, positions.
- **Sorties** : tableau Kanban fonctionnel.
- **Fichiers** : `app/Domain/Project/*`, composants Livewire.
- **Tests** : AC-8.1, AC-8.2.
- **Preuves** : tests + capture.
- **Acceptation** : statuts persistés, ordre conservé.
- **Risques** : concurrence d'ordre → verrouillage optimiste.

### T-027 — Journal quotidien
- **Objectif** : saisir les journées et les relier aux activités.
- **Dépend de** : T-025.
- **Entrées** : `daily_logs`, `daily_log_activities`.
- **Traitement** : CRUD, liens N-N, notes privées, unicité date/stage/auteur.
- **Sorties** : journal fonctionnel.
- **Fichiers** : `app/Domain/Journal/*`.
- **Tests** : AC-7.1, AC-7.2.
- **Preuves** : tests.
- **Acceptation** : notes privées jamais exposées.
- **Risques** : sérialisation accidentelle → ressource API dédiée.

### T-028 — Clôture et révisions
- **Objectif** : tracer les modifications après clôture.
- **Dépend de** : T-027.
- **Entrées** : statuts `draft`/`closed`.
- **Traitement** : clôture, révision auditée, conservation de l'historique.
- **Sorties** : journal clôturé avec révisions.
- **Fichiers** : service journal, audit.
- **Tests** : clôture, modification post-clôture auditée.
- **Preuves** : tests + audit.
- **Acceptation** : aucune perte d'information.
- **Risques** : complexité de versioning → limiter aux champs modifiés.

### T-029 — Tableau de bord
- **Objectif** : synthèse utile sans surcharge.
- **Dépend de** : T-023 → T-028.
- **Entrées** : UI-UX §9.
- **Traitement** : prochaines échéances, activité récente, alertes.
- **Sorties** : dashboard.
- **Fichiers** : `app/Http/Controllers/DashboardController.php`, vues.
- **Tests** : rendu, données filtrées par workspace.
- **Preuves** : tests.
- **Acceptation** : aucune donnée hors périmètre.
- **Risques** : requêtes N+1 → eager loading.

### T-030 — Campagne de tests phase 2
- **Objectif** : prouver stages, journal, Kanban.
- **Dépend de** : T-020 → T-029.
- **Entrées** : MT-04, MT-09.
- **Traitement** : exécution complète.
- **Sorties** : rapport de campagne.
- **Preuves** : sortie + evidence.
- **Acceptation** : tests PASS.
- **Risques** : couverture insuffisante → revue de matrice.

## Phase 3 — Traçabilité

### T-040 — Stockage des preuves
- **Objectif** : abstraction de stockage et upload sécurisé.
- **Dépend de** : T-030.
- **Entrées** : `EvidenceStorageInterface`, SECURITY §3.4.
- **Traitement** : LocalStorage, validation MIME/taille, noms aléatoires, hors webroot.
- **Sorties** : service d'upload et de téléchargement contrôlé.
- **Fichiers** : `app/Integrations/Storage/*`, `app/Domain/Evidence/*`.
- **Tests** : upload valide, type interdit rejeté, taille dépassée rejetée.
- **Preuves** : tests.
- **Acceptation** : aucun fichier exécutable stocké.
- **Risques** : MIME falsifié → détection réelle.

### T-041 — Preuves et visibilité
- **Objectif** : gérer les preuves et leur visibilité.
- **Dépend de** : T-040.
- **Entrées** : `evidences`.
- **Traitement** : CRUD, `demonstrated` obligatoire, visibilité, policies.
- **Sorties** : galerie et détail.
- **Fichiers** : `app/Domain/Evidence/*`, vues.
- **Tests** : MT-07, MT-08.
- **Preuves** : tests.
- **Acceptation** : preuve privée inaccessible au maître.
- **Risques** : URL devinable → contrôleur + policy.

### T-042 — Liens activité ↔ preuve
- **Objectif** : rattacher les preuves aux activités.
- **Dépend de** : T-041, T-025.
- **Entrées** : `activity_evidences`.
- **Traitement** : association N-N, note, isolation.
- **Sorties** : activités documentées.
- **Preuves** : tests.
- **Acceptation** : une preuve peut documenter plusieurs activités.
- **Risques** : doublons → contrainte unique.

### T-043 — Dépôts et connecteur GitHub
- **Objectif** : associer un dépôt et préparer l'import.
- **Dépend de** : T-024.
- **Entrées** : `repositories`, `GitProviderInterface`.
- **Traitement** : CRUD dépôts, jeton chiffré, test de connexion.
- **Sorties** : dépôt associé à un projet.
- **Fichiers** : `app/Integrations/Git/*`, `app/Integrations/GitHub/*`.
- **Tests** : jeton absent des réponses, dépôt inaccessible → erreur claire.
- **Preuves** : tests + absence de secret vérifiée.
- **Acceptation** : aucune fuite de jeton.
- **Risques** : limites API → backoff.

### T-044 — Import idempotent des commits
- **Objectif** : importer les commits sans doublon.
- **Dépend de** : T-043.
- **Entrées** : `commits`, `sync_runs`.
- **Traitement** : `upsert` sur `(repository_id, sha)`, pagination API, `sync_runs`.
- **Sorties** : commits importés, historique de synchronisation.
- **Tests** : MT-06.
- **Preuves** : sortie de test + double import.
- **Acceptation** : MT-06 PASS.
- **Risques** : rate limit → reprise partielle tracée.

### T-045 — Fichiers et associations
- **Objectif** : conserver les fichiers et lier commits ↔ activités.
- **Dépend de** : T-044, T-025.
- **Entrées** : `commit_files`, `activity_commits`.
- **Traitement** : import des fichiers, association manuelle.
- **Sorties** : traçabilité commit → activité.
- **Tests** : unicité, isolation, association multiple.
- **Preuves** : tests.
- **Acceptation** : plusieurs commits par activité.
- **Risques** : volume → pagination.

### T-046 — Campagne de tests phase 3 et Playwright
- **Objectif** : prouver la traçabilité.
- **Dépend de** : T-040 → T-045.
- **Entrées** : MT-06, MT-07, MT-08.
- **Traitement** : installation Playwright, parcours critiques.
- **Sorties** : rapports de tests.
- **Preuves** : sorties + rapport Playwright.
- **Acceptation** : tests PASS.
- **Risques** : navigateurs non installés → documenter NOT_PROVEN si échec.

## Phase 4 — Collaboration

### T-050 — Commentaires
- **Objectif** : commenter les activités partagées.
- **Dépend de** : T-030.
- **Entrées** : `comments`.
- **Traitement** : CRUD, visibilité, auteur/date immuables.
- **Sorties** : fil de commentaires.
- **Tests** : MT-09.
- **Preuves** : tests.
- **Acceptation** : auteur et date conservés.
- **Risques** : XSS → échappement.

### T-051 — Revues
- **Objectif** : demander et suivre une revue.
- **Dépend de** : T-050.
- **Entrées** : `reviews`.
- **Traitement** : statuts, feedback, notifications.
- **Sorties** : revues fonctionnelles.
- **Preuves** : tests.
- **Acceptation** : statuts cohérents.
- **Risques** : notifications manquantes → journalisation.

### T-052 — Permissions de stage effectives
- **Objectif** : appliquer l'intersection rôle workspace ∩ rôle stage ∩ visibilité.
- **Dépend de** : T-006, T-023, T-041, T-050.
- **Entrées** : matrice rôles.
- **Traitement** : policies, permissions JSON, tests exhaustifs.
- **Sorties** : droits effectifs vérifiés.
- **Tests** : MT-03 complet.
- **Preuves** : tests.
- **Acceptation** : aucune élévation de privilège.
- **Risques** : combinaisons oubliées → tests paramétrés.

### T-053 — Révocation et conservation
- **Objectif** : révoquer un participant sans perte d'historique.
- **Dépend de** : T-052.
- **Entrées** : INV-13.
- **Traitement** : révocation, statut, transfert documenté.
- **Sorties** : historique conservé.
- **Tests** : MT-10 complet.
- **Preuves** : tests.
- **Acceptation** : MT-10 PASS.
- **Risques** : suppression en cascade → interdite.

### T-054 — Vue maître de stage
- **Objectif** : offrir une vue limitée et utile.
- **Dépend de** : T-052.
- **Entrées** : APPFLOW §3.
- **Traitement** : dashboard dédié, filtres de visibilité.
- **Sorties** : interface maître de stage.
- **Tests** : aucune donnée privée affichée.
- **Preuves** : tests + capture.
- **Acceptation** : périmètre respecté.
- **Risques** : fuite par métadonnées → sérialisation contrôlée.

## Phase 5 — Pédagogie et documents

### T-060 — Activités et compétences
- **Objectif** : associer activités et compétences avec justification.
- **Dépend de** : T-030, T-022.
- **Entrées** : `activity_competencies`.
- **Traitement** : association, justification obligatoire, statut `mobilized`.
- **Sorties** : matrice compétences.
- **Tests** : INV-09 (pas de validation auto).
- **Preuves** : tests.
- **Acceptation** : aucune transition automatique.
- **Risques** : statuts confondus → libellés UI explicites.

### T-061 — Évaluations nominatives
- **Objectif** : évaluer une compétence de façon traçable.
- **Dépend de** : T-060.
- **Entrées** : `competency_assessments`.
- **Traitement** : évaluation par rôle habilité, date, appréciation, statut.
- **Sorties** : évaluations consultables.
- **Tests** : AC-11.1, AC-11.2.
- **Preuves** : tests + audit.
- **Acceptation** : évaluateur et date conservés.
- **Risques** : auto-évaluation → contrôle de rôle.

### T-062 — Modèles de rapports
- **Objectif** : gérer des modèles versionnés.
- **Dépend de** : T-030.
- **Entrées** : `report_templates`, `report_template_versions`.
- **Traitement** : CRUD admin, publication, immuabilité.
- **Sorties** : modèles disponibles.
- **Preuves** : tests.
- **Acceptation** : aucune trame codée en dur.
- **Risques** : schéma JSON invalide → validation.

### T-063 — Génération de rapports
- **Objectif** : produire une synthèse filtrée.
- **Dépend de** : T-062, T-052.
- **Entrées** : `reports`, INV-14.
- **Traitement** : job asynchrone, filtrage de visibilité, exclusions listées, audit.
- **Sorties** : rapport généré.
- **Tests** : MT-11.
- **Preuves** : tests + fichier généré.
- **Acceptation** : MT-11 PASS.
- **Risques** : fuite de notes privées → tests dédiés.

### T-064 — Exports PDF/DOCX
- **Objectif** : exporter les rapports.
- **Dépend de** : T-063.
- **Entrées** : rapports générés.
- **Traitement** : rendu PDF/DOCX, stockage privé, téléchargement contrôlé.
- **Sorties** : fichiers exportables.
- **Tests** : contenu conforme, aucune donnée privée.
- **Preuves** : fichiers + tests.
- **Acceptation** : distinction faits/déclarations/appréciations.
- **Risques** : bibliothèque PDF non compatible PHP 8.5 → vérification préalable.

### T-065 — Sauvegarde et restauration
- **Objectif** : prouver la récupération des données.
- **Dépend de** : T-002, T-041.
- **Entrées** : base + fichiers de preuves.
- **Traitement** : script de sauvegarde, procédure de restauration documentée.
- **Sorties** : sauvegarde et restauration testées.
- **Tests** : MT-12.
- **Preuves** : sortie + contrôle d'empreintes.
- **Acceptation** : MT-12 PASS.
- **Risques** : fichiers volumineux → incrémental.

### T-066 — Accessibilité et E2E
- **Objectif** : valider WCAG 2.2 AA et parcours.
- **Dépend de** : T-029 → T-064.
- **Entrées** : UI-UX §6, parcours Playwright.
- **Traitement** : audit automatique + manuel, corrections.
- **Sorties** : rapport d'accessibilité.
- **Preuves** : rapport + tests.
- **Acceptation** : critères applicables respectés.
- **Risques** : audit partiel → périmètre documenté.

## Transverse

### T-070 — Revue de sécurité
- **Objectif** : revue indépendante avant release.
- **Dépend de** : T-066.
- **Entrées** : SECURITY, OWASP ASVS.
- **Traitement** : revue des 8 tests de sécurité, vulnérabilités et gravités.
- **Sorties** : rapport de sécurité.
- **Preuves** : rapport + preuves d'exécution.
- **Acceptation** : aucune vulnérabilité bloquante ouverte.
- **Risques** : angle mort → checklist ASVS.

### T-071 — Rapport de release
- **Objectif** : documenter la livraison.
- **Dépend de** : T-070.
- **Entrées** : état Git, migrations, tests, gates, documentation.
- **Traitement** : checklist release, rapport structuré.
- **Sorties** : rapport de release.
- **Preuves** : rapport + approbations.
- **Acceptation** : aucune condition BLOCKED/FAIL/NOT_PROVEN non justifiée.
- **Risques** : précipitation → gate bloquant.
