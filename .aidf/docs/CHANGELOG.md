---
doc: CHANGELOG
status: VALIDATED
version: 1.0.0
source: stage-tracker-specification
updated: 2026-11-08
validated_by: Jeremy Ferrier
---

# CHANGELOG

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) ;
versionnement [SemVer](https://semver.org/lang/fr/).

## [Unreleased]

### À venir
- Modules métier : formations, référentiel, stages, activités.
- Gestion fine des membres : rôles, suspension (T-021).

## [1.1.0] — 2026-11-13 — Phase 3 : preuves, visibilité et liaison activités (T-040 → T-042)

### Ajouté
- T-040 : abstraction de stockage `EvidenceStorageInterface` + implémentation
  locale `LocalEvidenceStorage` (fichiers hors webroot, clés aléatoires UUID,
  liée au conteneur DI dans `AppServiceProvider`). Validation d'upload :
  taille max 10 Mo et liste d'extensions autorisées (images, PDF, documents,
  archives).
- T-041 : CRUD des preuves (`evidence.created/updated/archived` audités),
  `demonstrated` obligatoire, visibilité `private/stage/shared`, galerie et
  détail, archivage (jamais de suppression physique).
- T-042 : liaison N-N preuve ↔ activité avec note, contrainte unique
  `(activity_id, evidence_id)` (idempotence via `firstOrCreate`) ; une preuve
  peut documenter plusieurs activités ; section « Preuves liées » dans la
  fiche d'activité.
- Téléchargement contrôlé : `GET /evidences/{evidence}/download` soumis à la
  policy `EvidencePolicy::view` (MT-07 : preuve privée inaccessible au maître
  de stage en liste, détail et téléchargement direct ; MT-08 : preuve partagée
  téléchargeable par un membre autorisé).
- Policy `EvidencePolicy` (view/manage), modèle `ActivityEvidence`, sidebar :
  lien « Preuves » actif.
- Jeu de démo : deux preuves (une partagée liée à la maquette, une privée).
- 6 tests `EvidenceTest` (upload, type interdit, taille dépassée, MT-07,
  MT-08, liaison N-N) ; **104 tests PASS / 449 assertions**.

### Corrigé
- Routes preuves placées dans le groupe `w/{workspace}` (précédemment hors
  contexte workspace) ; noms de tables explicites `evidences`/`activity_evidences`
  (le pluralisateur Eloquent traite « evidence » comme invariable).

### Sécurité
- Upload : extensions et taille contrôlées côté serveur ; noms de fichiers
  aléatoires (UUID) — l'URL n'est jamais devinable ; téléchargement uniquement
  via le contrôleur (policy) ; fichiers hors webroot (disque `local`).

## [1.0.0] — 2026-11-13 — Phase 2 complète : stages et suivi (T-023 → T-030)

### Ajouté
- Campagne de tests phase 2 (T-030) : vérification consolidée des invariants
  MT-03, MT-04, MT-05 ; 98 tests PASS / 426 assertions au total.
- Tableau de bord consolidé (T-029) : indicateurs stages/activités/journées/
  référentiels et raccourcis.
- README : §2 (Phase 2 complète).

### Modifié
- Aucune migration nouvelle dans ce lot (consolidation).

## [0.9.0] — 2026-11-13 — Tableau de bord consolidé (T-029)

### Ajouté
- `WorkspaceController::dashboard()` enrichi : indicateurs (stages total/en cours/terminés,
  activités par statut Kanban, journées récentes, référentiels appliqués aux
  stages de l'espace) et raccourcis (Stages, Formations, Organisations, Membres,
  Audit).
- Vue `workspace/dashboard.blade.php` réécrite avec cartes d'indicateurs.
- README : §2, §16.

### Modifié
- Aucune migration.

## [0.8.0] — 2026-11-13 — Tâches Kanban, journal quotidien et clôture (T-026 → T-028)

### Ajouté
- Migrations `tasks`, `daily_logs`, `daily_log_activities` (FK composites,
  contraintes de statut/type/priorité, durées, unicité
  `(internship, auteur, date)`).
- Domaine `Project` : modèle `Task` + `TaskController` (création, changement
  de statut via liste déroulante, suppression, position) ; Kanban dans la
  fiche d'activité.
- Domaine `Journal` : `DailyLog`, `DailyLogActivity`, `DailyLogController`
  (création, modification, clôture, liaison d'activités avec contribution).
- Clôture d'une journée : passage `draft → closed` avec `closed_at` ; une
  journée clôturée devient en lecture seule (HTTP 403 sur édition).
- Vues : `daily_logs/*` (index, create, show, edit), section Kanban dans
  `activities/show`.
- Lien « Journal » depuis la fiche d'un stage.
- 4 tests (`DailyLogTest`) : création, unicité, lecture seule après clôture,
  clôture effective.
- README : §2, §4, §5.4, §6, §10.11/10.12, §16, §17.

### Modifié
- `Internship` : relation `dailyLogs()`.
- `Activity` : relation `tasks()` + méthode `priorityLabel()`.
- `Workspace` : (pas de changement de relation ; les tâches/journaux sont
  accessibles via le stage).
- `AuditLogger` : réutilisation des clés `subject`, `status`, `changed`.

### Sécurité
- Isolation multi-tenant maintenue sur tâches et journaux (FK composites +
  résolution par workspace).
- Une journée clôturée ne peut plus être modifiée (garde applicative).

## [0.7.0] — 2026-11-13 — Stages, projets et activités (T-023 → T-025)

### Ajouté
- Migrations `internships`, `internship_participants`, `projects`, `activities`
  (FK composites, contraintes de statut/type/priorité, `UNIQUE` par
  workspace).
- Domaine `Internship` : `Internship`, `InternshipParticipant`, controllers,
  vues (index/create/show/edit) ; ajout de participants (stagiaire, maître de
  stage, tuteur) avec retrait ; permission de modification partagée entre
  propriétaires/administrateurs et stagiaire concerné.
- Domaine `Project` : `Project`, `Activity`, controllers, vues (index/create/
  show/edit) ; activités avec type, statut Kanban, priorité, période,
  objectifs, résultats, difficultés, solutions et archivage.
- Navigation : lien « Stages » dans la barre latérale (retiré de la liste
  « à venir »).
- Jeu de démonstration étendu : stage « Stage Démo — Développeur Web »,
  participant superviseur, projet « Site vitrine » et deux activités.
- 4 tests (T-023/T-024/T-025) : création, refus pour un stagiaire, isolation
  inter-workspaces, MT-04 (deux stages d'un même stagiaire restent
  strictement séparés).
- README : §2, §4, §5.4, §6, §10.9/10.10, §16, §17.

### Modifié
- `Workspace` : relation `internships()`.
- `AuditLogger` : métadonnées `subject`, `status`, `type`, `changed` déjà
  autorisées (réutilisées pour les nouveaux événements `internship.*`,
  `project.*`, `activity.*`).

### Sécurité
- Isolation multi-tenant maintenue sur stages/projets/activités (FK composites
  + résolution par workspace).

## [0.6.0] — 2026-11-13 — Formations, référentiels versionnés et publication immuable (T-020 → T-022)

### Ajouté
- Migrations `training_programs` et `training_enrollments` (FK composites,
  contrainte d'unicité `(user_id, training_program_id)`, statuts
  `active/completed/withdrawn`).
- Domaine `Training` : `TrainingProgram`, `TrainingEnrollment`, controllers,
  requêtes, vues (`training/index`, `create`, `edit` avec gestion des
  inscriptions et changement de statut).
- Migrations `competency_frameworks`, `framework_versions`, `competency_blocks`,
  `competencies`, `competency_criteria` (statuts, contraintes d'unicité,
  CHECK `published ⇒ published_at`).
- Domaine `Framework` : modèles, `FrameworkVersionService` (publication
  atomique `draft → published`), controllers, requêtes, vues (`frameworks/index`,
  `create`, `edit`, `show` avec édition de la hiérarchie blocs/compétences/
  critères et publication).
- Trigger PostgreSQL d'immuabilité T-022 : refus de tout `UPDATE`/`DELETE`
  sur une version publiée et sur son contenu (blocs, compétences, critères),
  y compris en accès SQL direct (INV-08). Preuve PHPUnit + erreur PostgreSQL
  observée.
- Politique `CompetencyFrameworkPolicy` : administration réservée aux
  propriétaires/administrateurs d'un espace actif (DEC-ST-015).
- Jeu de démonstration étendu : programme « Formation Démo (DWWM) »,
  inscription du stagiaire, référentiel « Référentiel Démo (pilote) » v1
  avec bloc/compétence/critère.
- 11 tests (T-020/T-021/T-022) : création, isolation, unicité d'inscription,
  rejet des non-membres, hiérarchie, doublons, immuabilité HTTP et SQL,
  MT-05 (v1 publiée intacte lors de la publication de v2).
- README : §2, §4, §5.4, §6, §10.7/10.8, §16, §17.

### Modifié
- `WorkspacePolicy` : ajout de `manageTraining` (propriétaires/administrateurs).
- `AuditLogger` : liste blanche étendue (`training_program_id`,
  `training_program_name`, `enrollment_id`, `framework_id`, `framework_name`,
  `version`).
- Navigation : lien « Formations » dans la barre latérale, lien
  « Référentiels » dans la barre supérieure (selon rôle).
- `composer audit` : 0 vulnérabilité (Livewire 4.4.8 installé, DEC-ST-014
  ACCEPTED).

### Sécurité
- Aucune écriture possible sur une version publiée, y compris via SQL direct
  (trigger PostgreSQL + garde applicative).
- Isolation multi-tenant maintenue sur les programmes de formation (FK
  composite + résolution par workspace).

## [0.5.0] — 2026-11-08 — Données de test, interface navigable, campagne multi-tenant (T-010 → T-012)

### Ajouté
- Factory `OrganizationMemberFactory` (workspace synchronisé avec
  l'organisation) et trait `HasFactory` sur le modèle.
- Seeder `DemoSeeder` neutre et idempotent : « Espace Démo » avec 4 comptes de
  démonstration (`@example.test`), 4 appartenances (owner/trainee/supervisor/
  tutor) et 2 organisations fictives ; aucun élément COAB ; aucune entrée
  d'audit écrite par le seed (vérifié `migrate:fresh --seed --env=testing`).
- Garde d'environnement : le seeding est refusé en dehors de `local`/`testing`
  (RuntimeException), couvert par un test.
- Ossature d'interface (T-011) : layout Blade unique avec barre supérieure
  (Mes espaces, compte, déconnexion), navigation latérale par espace
  (Tableau de bord, Organisations, Membres/audit selon rôle, sections
  « à venir » non cliquables), sélecteur « Espace actif » listant les autres
  espaces avec leur rôle, fil d'Ariane `<x-breadcrumb>`, lien « Aller au
  contenu », menu mobile repliable, styles d'imbrication `aria-current`.
- Build frontend : `package.json` + `vite.config.js` (vite 6.4.5,
  laravel-vite-plugin 1.3.0), sources `resources/css/app.css` (design system
  CSS maison conforme UI-UX) et `resources/js/app.js` ; `npm run build`
  vérifié. Fallback `public/css/app.css` servi lorsque le build Vite est absent
  (suite de tests sans Node).
- Campagne de tests multi-tenant (T-012) :
  `tests/Feature/Tenant/MultiTenantCampaignTest.php` couvrant MT-01 (member
  of A never sees B), MT-02 (authenticated non-member: 403 partout, aucune
  fuite d'existence, aucune écriture), MT-03 (rôles non-admin sans écriture,
  admin opérationnel) et MT-10 (revocation: accès perdu, aucune cascade,
  audit consultable et append-only).

### Modifié
- Vues espace reprenant le `<x-breadcrumb>` (tableau de bord, organisations,
  membres, audit).
- Vérifications CSRF prouvées hors PHPUnit (le framework ignore la vérification
  en milieu de test) : `POST /login` sans jeton → HTTP 419 en runtime.
- `composer audit` : 0 vulnérabilité après mise à jour dirigée
  symfony/http-foundation, http-kernel, routing.
- Décision DEC-ST-014 (DRAFT) : version Livewire reportée à arbitrage humain
  avant T-020 ; interfaces actuelles en Blade pur.
- README : §2 (statuts), §4 (installation frontend + jeu de démo), §5.4
  (navigation), §6 (concepts), §16 (structure + commandes), §17 (limites).

### Sécurité
- Aucune donnée réelle dans les seeders ; garde anti-seed-production testée.
- `composer audit` : aucun avis de sécurité restant.

## [0.4.0] — 2026-11-08 — Invitations, organisations, audit (T-007 → T-009)

### Ajouté
- Migrations `invitations` (token haché SHA-256, expiration 7 jours, index
  partiel d'unicité des invitations en attente) et `organizations` /
  `organization_members` (FK composite `(organization_id, workspace_id)`).
- Migration `audit_logs` append-only : trigger PostgreSQL refusant tout
  `UPDATE`/`DELETE`, index `(workspace_id, created_at DESC)`.
- Service `InvitationService` : création/renouvellement, acceptation avec le rôle
  exact proposé, refus, annulation ; e-mail `WorkspaceInvitationMail`.
- Parcours d'invitation : page publique `/invitations/{token}` (200/404/410),
  acceptation/refus authentifiés avec vérification de l'adresse, création de
  compte possible depuis l'invitation (aucune inscription publique ouverte).
- Règles de rôles proposables : `owner` → admin/trainee/supervisor/tutor/viewer ;
  `admin` → non-administrateur uniquement ; rôle `owner` jamais proposable.
- CRUD organisations : liste, création, modification, archivage (aucune
  suppression physique), résolution par workspace (isolation).
- Service `AuditLogger` : liste blanche des clés de métadonnées, IP, user-agent,
  acteurs, cible ; aucune donnée sensible en audit (e-mail → empreinte).
- Journalisation : `auth.login`, `auth.logout`, `auth.login.failed`,
  `auth.login.throttled`, `workspace.created`, `workspace.access.denied`,
  `user.created`, `invitation.*`, `organization.*`.
- Policies `Workspace` étendues : `manageOrganizations`, `viewAudit`.
- Page membres `/w/{workspace}/members` (invite + liste + annulation),
  page organisations `/w/{workspace}/organizations`, page audit
  `/w/{workspace}/audit` (pagination, 50 entrées).
- Lien « Connexion pour accepter l'invitation » avec redirection locale sûre
  (`/…` uniquement, pas de redirect ouvert).

### Vérifié
- `php artisan test` → 59 tests PASS, 230 assertions (30 nouveaux :
  InvitationTest, OrganizationTest, AuditLogTest).
- `vendor/bin/pint --test` → PASS.
- `php artisan migrate` → 9 migrations DONE sur PostgreSQL 16.15.
- Trigger append-only prouvé hors application : `UPDATE`/`DELETE` direct en psql
  → `ERROR: audit_logs est append-only` (ligne de contrôle ensuite neutralisée
  en désactivant le trigger, puis réactivé).
- Smoke HTTP : `/login` 200, `/` 302 → `/login`.
- Base de développement propre : 0 utilisateur, 0 espace, 0 invitation,
  0 organisation, 0 ligne d'audit.

### Limites
- Gestion fine des membres (changement de rôle, suspension) à venir (T-021).
- Envoi réel d'e-mails non configuré (`MAIL_MAILER=log` en local).
- L'audit visible par espace ; pas de vue d'audit globale transverse.
- Frontend Vite/npm non installé (T-011).

## [0.3.0] — 2026-11-04 — Authentification et isolation (T-004 → T-006)

### Ajouté
- Migrations `users` (`password_hash`, statut, `last_login_at`), `sessions`, `cache`, `jobs`.
- `workspaces` (UUID) et `workspace_members` avec contraintes CHECK (rôles, statuts).
- Authentification : connexion/déconnexion, Argon2id, régénération de session, `last_login_at`.
- Rate limiting 5 tentatives / 15 minutes → HTTP 429 avec `Retry-After`.
- Messages d'erreur neutres (compte inconnu, mot de passe faux, compte désactivé).
- Middleware `EnsureWorkspaceMember` et route `/w/{workspace}/dashboard`.
- `WorkspaceContext` + trait `BelongsToWorkspace` (scope global, remplissage automatique).
- Policies `Workspace` (view/update/manageMembers/delete) et `AuthServiceProvider`.
- Commandes `app:create-user` et `app:create-workspace`.
- Vues Blade : connexion, mes espaces, tableau de bord d'espace.
- Front controller `public/index.php`, `.htaccess`, `robots.txt` (absents du squelette Laravel 13).
- `.gitignore` pour `bootstrap/cache` et `storage` (artefacts runtime non versionnés).

### Vérifié
- `php artisan test` → 29 tests PASS, 85 assertions.
- `vendor/bin/pint --test` → PASS.
- `php artisan migrate` → 5 migrations DONE sur PostgreSQL 16.15.
- `php artisan serve` + HTTP → `/login` 200, `/` 302 (invité).
- `app:create-user` et `app:create-workspace` exécutées réellement (données de démo supprimées).

### Limites
- Pas d'invitation ni de gestion des membres en interface (T-021).
- Audit persistant en base à venir (T-009) ; logs transitoires seulement.
- Frontend Vite/npm non installé (T-011).
- Aucun module métier (organisations, stages, journal, preuves) à ce stade.

## [0.2.0] — 2026-11-04 — Socle technique (T-001 → T-003)

### Ajouté
- Squelette Laravel 13.40.0 fusionné dans le dépôt sans écraser la gouvernance.
- Structure `app/Domain/*` (12 domaines) et `app/Integrations/*` (Git, GitHub, Storage).
- PostgreSQL 16.15 via Docker (`stage-tracker-postgres`, healthcheck healthy).
- `phpunit.xml` (base de test `stage_tracker_test`), `.env.example` complété.
- Page d'accueil minimale en français.
- Migration/rollback vérifiés (migration technique temporaire, supprimée).

### Vérifié
- `php artisan --version` → Laravel Framework 13.40.0.
- `php artisan db:show` → PostgreSQL 16.15, connexion `pgsql`.
- `php artisan test` → 2 tests PASS, 2 assertions.
- `vendor/bin/pint --test` → PASS.
- `docker compose config` → VALID ; conteneur `healthy`.

### Limites
- Aucune fonctionnalité métier (T-004 et suivantes).
- Frontend Vite/npm non installé (T-011).
- Migrations métier non créées (T-004+).

## [0.1.0] — 2026-11-04 — Spécification

### Ajouté
- Installation AIDF dédiée au projet (`projets/stage-tracker/.aidf`).
- Dépôt Git initialisé (`main`).
- `docker-compose.yml` PostgreSQL 16 + `.env.example` + `.gitignore` + README.
- 11 documents AIDF : constitution, PRD, TRD, APPFLOW, UI-UX, BACKEND, PLAN,
  SECURITY, CONVENTIONS, DECISIONS, CHANGELOG.
- Annexes : ADR-001, ADR-002, parcours utilisateurs, matrice rôles/permissions,
  MCD, MLD, dictionnaire de données, contrats API, invariants INV-01..INV-14,
  critères d'acceptation, spécifications de tests MT-01..MT-12.
- Preuve de compatibilité PHP 8.5.12 ↔ Laravel 13.40.0.
- Preuve d'approbation humaine des décisions D-ST-01 à D-ST-04.

### Décisions
- DEC-ST-001 à DEC-ST-012 (voir DECISIONS.md).

### Limites connues
- Aucun code applicatif (phase IMPLEMENTATION non démarrée).
- Conformité RGPD : NOT_PROVEN (analyse juridique requise).
- Trames de rapports officielles indisponibles.
- Playwright non vérifié sous Windows.
