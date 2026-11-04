---
doc: TRD
status: VALIDATED
version: 1.0.0
source: stage-tracker-specification
updated: 2026-11-04
validated_by: Jeremy Ferrier
---

# TRD — Technical Requirements Document

## 1. Socle technique retenu

| Élément | Choix | Version / preuve |
|---|---|---|
| Langage | PHP | 8.5.12 NTS (VS17 x64) — vérifié `php -v` |
| Framework | Laravel | 13.40.0 — `composer create-project` réel (2026-11-04) |
| Base de données | PostgreSQL | 16 (`postgres:16-alpine`, image Docker locale vérifiée) |
| Frontend | Blade + Livewire + JS ciblé | Livewire 3.x, Vite |
| Runtime build | Node.js | 24.21.0 / npm 11.19.0 |
| Tests backend | PHPUnit | via Laravel |
| Tests navigateur | Playwright | installation à vérifier en PLANNING (IN-07) |
| Conteneurisation | Docker Compose | Docker 29.8.2, engine actif |
| VCS | Git | 2.56.0 |

**Preuve de compatibilité** : `composer create-project laravel/laravel`
réussit sous PHP 8.5.12 ; contrainte `php: ^8.3` satisfaite ; `php artisan
--version` → `Laravel Framework 13.40.0`. Le seul échec observé concerne
`pdo_sqlite` (absent), sans incidence : la cible est PostgreSQL et `pdo_pgsql`
est présent.

**Note d'écart** : le prompt maître mentionne Laravel sans version. La version
réellement installable et prouvée est Laravel 13.40.0. Tout écart ultérieur
(montée de version) exige une nouvelle preuve.

## 2. Architecture applicative

**Monolithe modulaire** organisé par domaine métier. Frontières de
responsabilité claires, déploiement unique.

```
app/
  Domain/
    Identity/        (users, workspaces, memberships, invitations)
    Organization/    (organizations, members)
    Training/        (programs, enrollments)
    Framework/       (frameworks, versions, blocks, competencies, criteria)
    Internship/      (internships, participants)
    Project/         (projects, activities, tasks)
    Journal/         (daily_logs, links)
    Evidence/        (evidences, links, storage abstraction)
    Evaluation/      (activity_competencies, assessments, reviews)
    Reporting/       (templates, versions, reports)
    Collaboration/   (comments, invitations, reviews)
    Audit/           (audit_logs)
  Integrations/
    Git/             (GitProviderInterface)
    GitHub/          (GitHubConnector)
    Storage/         (EvidenceStorageInterface: Local, Remote futur)
  Http/
    Controllers/ Requests/ Middleware/
  Policies/
database/ migrations seeders factories
resources/ views css js
routes/ web.php api.php
tests/ Feature Unit Browser
docs/ architecture database security functional testing
```

Règles de dépendance : `Domain/*` ne dépend pas de `Http/*` ; les intégrations
sont derrière des interfaces ; aucun domaine n'importe un autre domaine de
façon circulaire.

## 3. Multi-tenant

- Chaque table métier porte `workspace_id` (directement ou via une relation
  vérifiable).
- Un **global scope** Eloquent filtre par workspace courant ; toute requête
  hors scope est interdite par conception.
- Contraintes SQL : clés étrangères composites incluant `workspace_id` pour
  rendre une relation inter-workspace **impossible** au niveau base.
- Politiques Laravel (`Policies`) + middleware de résolution du workspace
  courant (`/w/{workspace}` ou contexte de session explicite).
- Tests d'accès direct par identifiant (IDOR) obligatoires (MT-01, MT-02).

## 4. Modèle de données

Le modèle relationnel complet est décrit dans :
- `docs/database/MCD.md` (conceptuel, cardinalités)
- `docs/database/MLD.md` (physique, clés, contraintes, index)
- `docs/database/data-dictionary.md` (dictionnaire des champs)

Principes : clés primaires `bigint` (ou UUID pour les entités exposées),
horodatage systématique, suppression logique (`archived_at`) pour les données
historiques, unicité `(repository_id, sha)` pour les commits.

## 5. API REST

- Préfixe `/api/v1`, authentification par session (Sanctum stateful) et/ou
  jeton personnel selon usage ; réponses JSON, erreurs normalisées.
- Contrats détaillés : `docs/functional/api-contracts.md`.
- Versionnement par préfixe d'URL ; aucune rupture silencieuse.

## 6. Sécurité technique

- Mots de passe : `bcrypt`/`argon2id` via Laravel `Hash`.
- CSRF : middleware Laravel sur toutes les routes web ; API stateful protégée.
- XSS : échappement Blade par défaut ; aucune sortie brute non contrôlée.
- Injection : requêtes préparées Eloquent ; aucun SQL concaténé.
- Sessions : driver base de données, cookie `HttpOnly`, `SameSite=Lax`,
  `Secure` en production.
- Limitation de tentatives : `RateLimiter` sur connexion et endpoints sensibles.
- Uploads : validation MIME/taille, stockage hors webroot, noms aléatoires.
- Secrets : `.env` ignoré ; jetons GitHub chiffrés (`encrypted` cast) côté
  serveur ; jamais exposés au frontend.
- Journalisation : `audit_logs` pour actions sensibles (connexion, partage,
  export, suppression, import).

## 7. Intégration GitHub

- `GitProviderInterface` → `GitHubConnector` (V1), extensible GitLab.
- Import manuel déclenché par l'utilisateur, idempotent : `upsert` sur
  `(repository_id, sha)`.
- Données conservées : SHA, auteur Git, auteur GitHub si disponible, dates,
  message, branche observée, fichiers et types de modification, URL, état de
  synchronisation.
- Jetons : permissions minimales lecture seule ; stockage chiffré ; aucune
  écriture côté GitHub en V1.

## 8. Stockage des preuves

Interface `EvidenceStorageInterface` :
- `LocalStorage` (V1) : disque `local`, répertoire privé non servi directement.
- `RemoteStorage` (futur) : S3-compatible.
Métadonnées en base (`evidences.storage_key`), téléchargement contrôlé par
politique d'accès (jamais d'URL publique devinable).

## 9. Environnements

| Environnement | Usage | Base | Données |
|---|---|---|---|
| local | développement, pilote COAB | PostgreSQL Docker | réelles du pilote, workspace dédié |
| test | tests automatisés | PostgreSQL de test | factories uniquement |
| production (futur) | serveur privé / hébergement sécurisé | PostgreSQL | réelles, sauvegardées |

Seeders réservés aux environnements `local` (démo) et `test`. Aucune donnée
COAB dans les seeders.

## 10. Exigences de performance et d'exploitation

- Pagination par défaut : 25 éléments ; exports en file d'attente si volumineux.
- Index sur toutes les clés étrangères et colonnes de filtre fréquentes.
- Sauvegarde quotidienne de la base + fichiers de preuves ; restauration testée
  (MT-12).
- Migrations réversibles ou documentées ; aucune migration destructive sans
  autorisation explicite.
- Logs applicatifs rotatifs ; audit immuable en append-only.

## 11. Contraintes et risques techniques

- PHP 8.5 est très récent : les dépendances tierces doivent être vérifiées au
  moment de l'installation (preuve Composer requise).
- Livewire + Alpine.js pour l'interactivité ; éviter les SPA complexes.
- Playwright sous Windows : vérifier l'installation des navigateurs avant les
  tests E2E.
- PostgreSQL 16 via Docker : port 5432 local à libérer ; configuration par
  variables d'environnement.
