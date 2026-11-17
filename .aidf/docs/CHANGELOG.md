---
doc: CHANGELOG
status: VALIDATED
version: 1.0.0
source: stage-tracker-specification
updated: 2026-11-17
validated_by: Jeremy Ferrier
---

# CHANGELOG

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) ;
versionnement [SemVer](https://semver.org/lang/fr/).

## [Unreleased]

### À venir
- Audit accessibilité et parcours E2E (T-066), revue de sécurité (T-070), rapport de release (T-071), README final (T-072).

## [1.6.0] — 2026-11-17 — Phase 5 (T-060 → T-065) et simplification du périmètre (décision utilisateur)

### Ajouté
- **T-060/T-061** : évaluations de compétences — table `activity_competencies`
  (association à une activité avec **justification obligatoire**), table
  `competency_assessments` (appréciation nominative `validated` / `remediation`
  avec commentaire, signée par un évaluateur habilité) ; permission `assess`
  limitée aux participants supervisor/tutor du stage ; **INV-09** : aucune
  validation automatique (clôtures d'activité ou de journal sans effet) ;
  compétence évaluée non retirable ; matrice des compétences par stage ;
  audit `competency.associated|documented|unlinked` + `assessment.created`.
- **T-062/T-063** : modèles de rapports versionnés (`report_templates`,
  `report_template_versions`, `reports`) ; sections allowlistées
  (`ReportTemplate::ALLOWED_SECTIONS`) ; version publiée immuable ;
  génération asynchrone via job `GenerateReport` (statuts
  queued/generated/failed) ; **INV-14** : filtrage serveur des notes privées
  et preuves non partagées, exclusions listées dans le rapport, distinction
  faits / déclarations / appréciations ; audit `report_template.*` +
  `report.generated`.
- **T-064** : exports PDF (dompdf 3.1, `isRemoteEnabled = false`) et DOCX
  (PhpWord 1.1, lecteur XHTML strict avec document complet) sur chaque
  rapport généré.
- **T-065** : sauvegarde `app:backup` (base en JSONL par table + fichiers de
  preuves + `manifest.json` avec empreintes SHA-256) et restauration
  `app:restore` (vérification des empreintes **avant** toute écriture,
  insertion en ordre topologique des clés étrangères, recadrage des séquences,
  restitution des fichiers) avec `--dry-run` ; **MT-12** prouvé en tests
  (3 tests) et sur la base de démonstration réelle (43 tables, 76 lignes,
  75 fichiers restaurés à l'identique).

### Modifié — simplification du périmètre (décision utilisateur du 2026-11-17)
- **Suppression du module Formations** (`TrainingProgram`,
  `TrainingEnrollment`, routes, vues, tables) : outil orienté stage en
  entreprise, pas gestion de formation.
- **Suppression des Organisations d'accueil** (`Organization`,
  `OrganizationMember`) : remplacées par un champ texte
  `internships.company_name` « entreprise d'accueil ».
- **Suppression des référentiels versionnés** (`CompetencyFramework`,
  `FrameworkVersion`, `CompetencyBlock`, `CompetencyCriterion`,
  `FrameworkVersionService`, triggers d'immuabilité) : remplacés par une
  **liste simple de compétences par espace** (`competencies` :
  code + intitulé + description, unique par espace) définie par les
  administrateurs ; modèle `Competency` déplacé dans
  `App\Domain\Evaluation\Models`.
- Ajustements : FK `internships` simplifiée, navigation latérale (Formations,
  Organisations, Référentiels retirés ; lien « Compétences » ajouté),
  Dashboard sans carte « Référentiels appliqués », allowlist d'audit
  resserrée, tests réécrits (MT-01/MT-02/MT-03/MT-10 portent désormais sur
  les compétences).
- Impact : migration fraîche requise (`migrate:fresh --seed`) ; les données
  de formation, organisations et référentiels des installations précédentes
  ne sont pas reprises.

## [1.4.0] — 2026-11-16 — Phase 4 : collaboration, permissions effectives et vue maître de stage (T-050 → T-054)

### Ajouté
- **T-050** : commentaires d'activité (`comments`, FK composites
  anti-fuite inter-espaces) — création/suppression seulement, **immuables**
  (INV-12 : contenu, auteur et date non modifiables), visibilité
  `private` (auteur seul) / `stage` / `shared`, échappement automatique
  (aucun HTML interprété), tracés dans l'audit.
- **T-051** : revues d'activité (`reviews`, `TRANSITIONS` contrôlées :
  `requested → in_progress → approved|changes_requested`, état final
  approuvée) — demande réservée aux gestionnaires de l'activité
  (`create_activity`), réviseur choisi parmi les participants
  supervisor/tutor actifs, **feedback réservé au réviseur désigné** ;
  notifications = journal d'audit uniquement.
- **T-052** : service `EffectivePermissions`
  (`app/Domain/Authorization/EffectivePermissions.php`) — droits effectifs
  = intersection (rôle espace ∩ rôle stage ∩ visibilité) ; owner/admin non
  plafonnés par un rôle de participant ; supervisor/tutor/viewer limités
  aux stages où ils sont participants actifs ; les permissions JSON par
  participant ne peuvent que **restreindre** (seule exception : `export`,
  octroyable à supervisor/tutor 🔶) ; participant `removed` = accès zéro.
  Appliqué aux vues stage/projet/activité/dépôt et à la liste des stages
  (portée « stage attribué »).
- **T-053** : révocation de participant = passage à
  `internship_participants.status='removed'` (INV-13 : historique conservé,
  **aucune suppression en cascade** — l'ancienne suppression en dur est
  remplacée), réactivation possible, actions tracées
  (`internship.participant.revoked|reactivated`) ; le stagiaire du stage
  ne peut pas être révoqué (transfert non pris en charge).
- **T-054** : vue dédiée `Mes suivis` (`/w/{workspace}/supervisor`) pour
  supervisor/tutor — stages rattachés actifs, revues à traiter, activités
  récentes, preuves `stage|shared` seulement (les preuves privées sont
  exclues **en amont**, jamais de fuite de titre), journal sans
  `private_notes` ; lien sidebar conditionnel ; parcours Playwright avec
  capture d'écran (`e2e/supervisor-view.spec.ts`,
  `docs/screenshots/supervisor-dashboard.png`).
- Vues : cartes Commentaires + Revues sur la fiche activité, statut
  actif/révoqué + boutons Révoquer/Réactiver sur la fiche stage, formulaire
  tâches caché aux non-gestionnaires.
- Jeu de démo : 1 entrée de journal (avec notes privées) et preuve privée
  rattachée à l'activité démo (démontre les garanties §8/§14 du README).
- Tests : 45 nouveaux (`tests/Feature/Collaboration/`) — MT-09 (immuabilité
  + XSS échappé), MT-03 complet (matrice paramétrée, 19 combinaisons +
  restrictions JSON + révocation), MT-10 (révocation, réactivation,
  désactivation de compte), T-054 (périmètre + absence de données privées).

### Modifié
- `DELETE .../participants/{participant}` : révocation (statut `removed`)
  au lieu de la suppression en dur ; nouvelle route
  `POST .../participants/{participant}/reactivate`.
- Les privés ne sont plus visibles des gestionnaires : les commentaires
  `private` ne sont visibles que de leur auteur (cohérent avec la matrice
  preuves), les preuves privées uniquement de leur propriétaire.

### Notes
- Décision (matrice) : owner/admin ne sont pas plafonnés par un rôle de
  participant — documentée ici, matrice §3-§5 de `roles-permissions.md`
  reste la référence.
- 158 tests PHPUnit PASS / 646 assertions ; pint PASS ; Playwright 6/6
  PASS.

## [1.3.0] — 2026-11-15 — Phase 3 : campagne de tests et Playwright (T-046)

### Ajouté
- **T-046** : tests navigateur Playwright (Chromium headless shell 159,
  @playwright/test 1.65.1), configuration `playwright.config.ts`, 4 parcours
  critiques PASS :
  - `e2e/auth.spec.ts` : connexion/déconnexion, mot de passe erroné ;
  - `e2e/git-traceability.spec.ts` : MT-06 via l'UI — du tableau de bord à la
    fiche dépôt, 3 commits visibles, liaison activité affichée ;
  - `e2e/evidence-visibility.spec.ts` : MT-07/MT-08 via l'UI — preuve privée
    absente de la liste du maître de stage et refusée en 403 par URL directe,
    preuve partagée consultable.
- Script `npm run e2e` ; rapport HTML dans `e2e-report/` (ignoré par Git) ;
  helper partagé `e2e/helpers.ts` (connexion, ouverture de l'espace démo).

### Notes
- Le jeu de démo est requis (`php artisan migrate:fresh --seed`) : les tests
  s'appuient sur ses libellés, sans UUID codé en dur.
- 113 tests PHPUnit PASS / 497 assertions ; pint PASS ; Playwright 4/4 PASS.

## [1.2.0] — 2026-11-15 — Phase 3 : connecteur GitHub et traçabilité commits (T-043 → T-045)

### Ajouté
- **T-043** : dépôts Git associés aux projets (CRUD, fournisseur
  github/gitlab/other, branche par défaut), connecteur GitHub REST v3
  (`GitProviderInterface` + `GitHubProvider`, DEC-ST-007), test de connexion
  avec erreur explicite, jeton d'accès optionnel **chiffré côté serveur**
  (`token_encrypted`, masqué au sérialiseur, absent des vues et de l'audit).
- **T-044** : import idempotent des commits (INV-05 : unicité
  `(repository_id, sha)`, `imported_at` écrit à la création uniquement,
  associations commit ↔ activité conservées), tracé dans `sync_runs`
  (success/partial/failed, compteur, résumé d'erreur).
- **T-045** : fichiers de commits (`commit_files`, unicité `(commit_id, path)`,
  upsert sans doublon) et association manuelle commit ↔ activité
  (`activity_commits`, unicité, plusieurs commits par activité, FK composites
  anti-fuite inter-espaces).
- Routes imbriquées `w/{workspace}/.../projects/{project}/repositories...`,
  vues création/fiche dépôt, section « Dépôts Git » sur la fiche projet.
- Jeu de démo : dépôt + 3 commits + 1 liaison activité.
- Tests : 9 nouveaux (`tests/Feature/Git/`), dont MT-06 (double import sans
  doublon) et isolation inter-espaces.

### Notes
- Complément MLD §9 : colonne `token_encrypted` sur `repositories` (le MLD ne
  modélisait pas le stockage du jeton ; conforme au README §11 — jeton chiffré,
  jamais exposé).
- Piège outillé : Pint (`php_unit_method_casing`) renomme `testConnection()`
  en `test_connection()` dans les classes anonymes des fichiers de test — les
  faux fournisseurs vivent désormais dans `tests/Support/FakeGitProvider.php`.
- 113 tests PASS / 497 assertions ; pint PASS ; `migrate:fresh --seed` OK
  (dev + test).

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
