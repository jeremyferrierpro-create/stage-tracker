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
