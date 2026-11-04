# Stage Tracker — manuel utilisateur

> **État du manuel** : en construction. Chaque section est complétée à la fin de
> la tâche qui livre la fonctionnalité correspondante (règle DEC-ST-013).
> Les sections marquées `À VENIR` ne décrivent volontairement aucune
> fonctionnalité non implémentée.

---

## Sommaire

1. [Présentation](#1-présentation)
2. [Statut du projet et gouvernance](#2-statut-du-projet-et-gouvernance)
3. [Prérequis](#3-prérequis)
4. [Installation](#4-installation)
5. [Premier lancement](#5-premier-lancement)
6. [Concepts clés](#6-concepts-clés)
7. [Guide du stagiaire](#7-guide-du-stagiaire)
8. [Guide du maître de stage](#8-guide-du-maître-de-stage)
9. [Guide du tuteur pédagogique](#9-guide-du-tuteur-pédagogique)
10. [Guide de l'administrateur](#10-guide-de-ladministrateur)
11. [Connecteur GitHub](#11-connecteur-github)
12. [Rapports et exports](#12-rapports-et-exports)
13. [Sauvegarde et restauration](#13-sauvegarde-et-restauration)
14. [Sécurité et confidentialité](#14-sécurité-et-confidentialité)
15. [Dépannage](#15-dépannage)
16. [Développement et contribution](#16-développement-et-contribution)
17. [Limites connues](#17-limites-connues)

---

## 1. Présentation

**Stage Tracker** est une application web de suivi de stages et de traçabilité
des activités professionnelles. Elle permet à un stagiaire de :

- consigner ses journées de travail ;
- organiser ses activités techniques et ses tâches (Kanban) ;
- relier ses commits Git à ses activités ;
- conserver des preuves (captures, documents, rapports de tests, liens) ;
- associer ses réalisations aux compétences de son référentiel de formation ;
- produire des synthèses pour son mémoire, son dossier professionnel et sa
  présentation orale.

Un maître de stage peut consulter les éléments partagés, commenter et suivre
l'avancement. Un tuteur pédagogique peut suivre et évaluer les compétences.

**Chaîne de valeur** : Travail réalisé → Activité → Tâche → Commit → Fichier →
Preuve → Compétence → Évaluation → Synthèse.

### Ce que Stage Tracker n'est pas

- Un ERP de formation (contrats, paie, plannings d'école).
- Un clone de GitHub Projects.
- Un générateur automatique de mémoire ou de compétences : aucune compétence
  n'est déclarée acquise automatiquement.

*Statut : `DISPONIBLE` (documenté) — source PRD.*

---

## 2. Statut du projet et gouvernance

| Élément | État |
|---|---|
| Phase AIDF | IMPLEMENTATION en cours — T-001 → T-006 livrés |
| Code applicatif | socle Laravel 13.40.0 + authentification + isolation multi-tenant ; modules métier à venir |
| Base de données | PostgreSQL 16.15 (conteneur Docker `stage-tracker-postgres`) |
| Tests | `php artisan test` → 29 tests PASS (85 assertions) ; `pint --test` → PASS |
| Sécurité | Argon2id, rate limiting 5/15 min (429), sessions en base, messages neutres |
| Documents de référence | `.aidf/docs/` (11 documents validés) |
| Annexes techniques | `docs/` (MCD, MLD, API, invariants, tests, planning) |
| Preuves | `.aidf/evidence/` (chaîne de preuves SHA-256) |

Le projet est gouverné par **AIDF 0.1.0** : chaque phase possède un gate
d'entrée et de sortie, et aucune phase n'est déclarée terminée sans preuve.

Commandes de gouvernance utiles (exécutées dans le projet) :

```bash
node .aidf/bin/aidf.mjs doctor
node .aidf/bin/aidf.mjs workflow status
node .aidf/bin/aidf.mjs context --task "ma tâche"
node .aidf/bin/aidf.mjs validate --phase <phase> --kind exit --allow-exec
```

*Statut : `DISPONIBLE` — vérifié le 2026-11-04.*

---

## 3. Prérequis

Les versions suivantes ont été **réellement vérifiées** sur l'environnement de
développement du projet :

| Outil | Version vérifiée | Vérification |
|---|---|---|
| PHP | 8.5.12 (NTS, VS17 x64) | `php -v` |
| Extensions PHP | `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `ctype`, `curl`, `fileinfo`, `tokenizer`, `xml`, `bcmath` | `php -m` |
| Composer | 2.10.3 | `composer -V` |
| Node.js | 24.21.0 | `node --version` |
| npm | 11.19.0 | `npm --version` |
| Git | 2.56.0 | `git --version` |
| Docker | 29.8.2 (engine actif) | `docker --version`, `docker ps` |
| Image PostgreSQL | `postgres:16-alpine` | `docker images` |
| PostgreSQL en service | 16.15 (conteneur `stage-tracker-postgres`) | `php artisan db:show` |

> `pdo_sqlite` n'est pas requis : la base cible est PostgreSQL.

*Statut : `DISPONIBLE` — vérifié le 2026-11-04.*

---

## 4. Installation

> **Backend vérifié le 2026-11-04** (T-001 → T-003). La partie frontend
> (Vite/npm) sera ajoutée avec T-011.

### 4.1 Cloner le dépôt

```bash
git clone <url-du-depot> stage-tracker
cd stage-tracker
```

### 4.2 Installer les dépendances PHP

```bash
composer install
```

Exécuté : dépendances installées depuis `composer.lock`, découverte des paquets OK.

### 4.3 Configurer l'environnement

```bash
cp .env.example .env
php artisan key:generate
```

Renseigner `DB_PASSWORD` dans `.env` (aucun secret n'est versionné).

Exécuté : clé d'application générée.

### 4.4 Démarrer PostgreSQL

```bash
docker compose up -d
docker compose ps        # attendre l'état "healthy"
```

Exécuté : conteneur `stage-tracker-postgres` démarré, healthcheck `healthy`.

### 4.5 Créer le schéma

```bash
php artisan migrate
```

Exécuté : tables `users`, `sessions`, `cache`, `jobs`, `workspaces` et
`workspace_members` créées (T-004 → T-006).

> Les seeders sont réservés aux environnements `local` et `test` ; ils ne
> contiennent aucune donnée réelle.

### 4.6 Vérifier que le socle fonctionne

```bash
php artisan --version       # Laravel Framework 13.40.0
php artisan db:show         # PostgreSQL 16.15, connexion pgsql
```

*Statut : `DISPONIBLE` — vérifié le 2026-11-04.*

---

## 5. Premier lancement

> **Disponible** depuis T-004 → T-006 (vérifié le 2026-11-04).

### 5.1 Créer le premier compte

Aucune inscription publique : un administrateur crée les comptes.

```bash
php artisan app:create-user vous@example.test --name="Votre nom"
```

Le mot de passe est demandé de façon masquée (12 caractères minimum) et stocké
avec Argon2id. Éviter `--password=` (visible dans l'historique du shell).

### 5.2 Créer l'espace de travail initial

```bash
php artisan app:create-workspace "Mon stage" --owner=vous@example.test --type=personal
```

Types disponibles : `personal`, `association`, `professional`, `pedagogical`.
Le créateur devient automatiquement membre avec le rôle `owner`.

### 5.3 Se connecter

```bash
php artisan serve
```

Ouvrir <http://127.0.0.1:8000> : la page de connexion s'affiche. Après
connexion, « Mes espaces » liste les espaces dont vous êtes membre actif.

### 5.4 Naviguer

1. Se connecter avec l'e-mail et le mot de passe créés.
2. Ouvrir un espace depuis « Mes espaces ».
3. L'URL `/w/{workspace}/dashboard` est refusée (403) si vous n'êtes pas membre
   actif de cet espace.

### 5.5 Sécurité de connexion

- Mots de passe hachés avec Argon2id.
- 5 échecs en 15 minutes → réponse HTTP 429 avec `Retry-After`.
- Message d'erreur identique pour un compte inconnu, un mot de passe faux ou un
  compte désactivé (aucune fuite d'existence).
- Sessions stockées en base ; identifiant de session régénéré à la connexion.
- `last_login_at` mis à jour à chaque connexion réussie (table d'audit en T-009).

*Statut : `DISPONIBLE` — vérifié le 2026-11-04.*

---

## 6. Concepts clés

> `À VENIR — livré par T-023 → T-027` (les concepts seront illustrés par les
> écrans réels).

Définitions déjà stabilisées par la spécification :

| Concept | Définition |
|---|---|
| **Workspace** | espace de travail isolé (personnel, associatif, professionnel, pédagogique) |
| **Stage** | période de stage rattachée à un workspace, un stagiaire, une organisation d'accueil et une version de référentiel |
| **Projet** | travail mené pendant le stage, contenant des activités |
| **Activité** | unité de travail documentée (fonctionnalité, correction, étude, conception, recherche, maintenance, amélioration) |
| **Tâche** | élément du Kanban, éventuellement rattaché à une activité |
| **Journal** | saisie quotidienne des travaux, difficultés, solutions et résultats |
| **Preuve** | élément vérifiable (capture, document, commit, rapport) avec une visibilité |
| **Compétence** | élément d'une version de référentiel ; peut être mobilisée, documentée ou évaluée |
| **Référentiel** | cadre de compétences versionné (ex. DWWM en pilote) |

*Statut : `DISPONIBLE` (définitions) — détails et captures `À VENIR`.*

---

## 7. Guide du stagiaire

> `À VENIR — livré par T-023 → T-042`.

Sections prévues :
- créer un stage et inviter son maître de stage ;
- saisir une journée de journal (brouillon puis clôture) ;
- documenter une activité ;
- utiliser le Kanban ;
- déposer une preuve et choisir sa visibilité ;
- associer une compétence avec justification ;
- générer une synthèse.

*Statut : `À VENIR`.*

---

## 8. Guide du maître de stage

> `À VENIR — livré par T-007, T-050 → T-054`.

Sections prévues :
- accepter une invitation ;
- consulter uniquement les éléments partagés ;
- commenter et demander une précision ;
- limites d'accès (notes privées, preuves privées).

*Statut : `À VENIR`.*

---

## 9. Guide du tuteur pédagogique

> `À VENIR — livré par T-060, T-061`.

Sections prévues :
- consulter les compétences mobilisées et documentées ;
- saisir une appréciation nominative ;
- comprendre la distinction mobilisée / documentée / évaluée.

*Statut : `À VENIR`.*

---

## 10. Guide de l'administrateur

> **Partiel** — base livrée par T-006 ; complété par T-009, T-021, T-062.

### 10.1 Rôles d'un espace de travail

| Rôle | Consulter | Modifier l'espace | Gérer les membres | Supprimer |
|---|---|---|---|---|
| `owner` | oui | oui | oui | oui |
| `admin` | oui | oui | oui | non |
| `trainee` | oui | non | non | non |
| `supervisor` | oui | non | non | non |
| `tutor` | oui | non | non | non |
| `viewer` | oui | non | non | non |

Un membre `suspended`, `invited` ou `removed` n'a aucun accès. Les rôles sont
contextualisés : un utilisateur peut être `owner` dans un espace et `trainee`
dans un autre.

### 10.2 Créer un compte

```bash
php artisan app:create-user email@example.test --name="Nom complet"
```

Aucune inscription publique. Un compte `disabled` conserve son historique
(INV-13) mais ne peut plus se connecter.

### 10.3 Créer un espace et nommer le propriétaire

```bash
php artisan app:create-workspace "Nom de l'espace" --owner=email@example.test --type=association
```

### 10.4 Inviter des participants

`À VENIR — T-021` (invitations, rôles, suspension).

### 10.5 Référentiels et organisations

`À VENIR — T-012 → T-018`.

### 10.6 Journal d'audit

`À VENIR — T-009`. Les connexions sont journalisées de façon transitoire dans
`storage/logs/laravel.log` (`auth.login`, `auth.logout`, `auth.login.failed`,
`auth.login.throttled`).

*Statut : `PARTIEL` — matrice des rôles et création de comptes/espaces vérifiées
le 2026-11-04.*

---

## 11. Connecteur GitHub

> `À VENIR — livré par T-043 → T-045`.

Sections prévues :
- créer un jeton GitHub en lecture seule ;
- associer un dépôt à un projet ;
- lancer une synchronisation ;
- vérifier l'absence de doublons (import idempotent) ;
- associer des commits à une activité.

> Le jeton est stocké côté serveur, chiffré, et n'est jamais exposé au
> navigateur.

*Statut : `À VENIR`.*

---

## 12. Rapports et exports

> `À VENIR — livré par T-062 → T-064`.

Sections prévues :
- choisir un modèle (mémoire, dossier professionnel, oral, bilan, suivi) ;
- générer une synthèse ;
- comprendre les exclusions (notes privées, preuves non partagées) ;
- distinguer faits, déclarations personnelles et appréciations de tiers.

*Statut : `À VENIR`.*

---

## 13. Sauvegarde et restauration

> `À VENIR — livré par T-065`.

Sections prévues :
- sauvegarder la base et les fichiers de preuves ;
- restaurer sur un environnement vierge ;
- vérifier l'intégrité par empreintes.

*Statut : `À VENIR`.*

---

## 14. Sécurité et confidentialité

> `À VENIR — livré par T-041, T-052, T-070`.

Sections prévues :
- comprendre les trois niveaux de visibilité (`private`, `stage`, `shared`) ;
- protéger ses notes personnelles ;
- gérer les accès de son maître de stage ;
- bonnes pratiques sur les jetons et les secrets.

*Statut : `À VENIR`.*

---

## 15. Dépannage

Faits déjà observés sur l'environnement de développement :

| Symptôme | Cause | Solution |
|---|---|---|
| `npm` ou `composer` refusé par la stratégie PowerShell | scripts `.ps1` bloqués | utiliser `npm.cmd` / `composer.bat` |
| `pdo_sqlite` absent | extension non installée | sans impact : la base cible est PostgreSQL (`pdo_pgsql` présent) |
| `psql` introuvable | PostgreSQL non installé nativement | utiliser Docker (`docker compose up -d`) |
| port 5432 déjà utilisé | instance locale existante | arrêter le service ou changer `DB_PORT` |

> Les erreurs propres à l'application seront ajoutées au fil des tâches, avec
> le message réel observé et la solution vérifiée.

*Statut : `DISPONIBLE` (environnement) — application `À VENIR`.*

---

## 16. Développement et contribution

### 16.1 Structure du dépôt

```
.aidf/            gouvernance AIDF, documents, preuves, workflow
docs/             architecture, base de données, fonctionnel, tests, planning
app/Console/      commandes artisan (comptes, espaces)
app/Domain/Identity/  utilisateurs, workspaces, appartenances, contexte courant
app/Domain/       autres domaines métier (Organization, Training, Framework,
                  Internship, Project, Journal, Evidence, Evaluation,
                  Reporting, Collaboration, Audit)
app/Http/         contrôleurs, middlewares, requêtes de formulaire
app/Integrations/ Git, GitHub, Storage
app/Models/Concerns/  traits transverses (BelongsToWorkspace)
app/Policies/     autorisations par ressource
bootstrap/        démarrage de l'application
config/           configuration
database/         migrations, factories, seeders
public/           point d'entrée web (index.php)
resources/        vues, styles, scripts
routes/           web.php (api.php à ajouter avec l'API)
storage/          logs, cache, fichiers privés
tests/            Feature, Unit, Support
vendor/           dépendances PHP (ignoré par Git)
docker-compose.yml
.env.example
phpunit.xml
```

### 16.2 Commandes de qualité

```bash
vendor/bin/pint --test      # style — PASS le 2026-11-04
php artisan test            # tests PHPUnit — 29 tests PASS (85 assertions) le 2026-11-04
npx playwright test         # navigateur — à activer en T-046/T-066
```

Base de test isolée (une seule fois) :

```bash
docker exec stage-tracker-postgres psql -U stage_tracker -d postgres \
  -c "CREATE DATABASE stage_tracker_test OWNER stage_tracker;"
```

Créer `.env.testing` à partir de `.env` avec `APP_ENV=testing` et
`DB_DATABASE=stage_tracker_test` (fichier ignoré par Git).

### 16.3 Conventions

- Commits : Conventional Commits (`feat:`, `fix:`, `docs:`, `test:`…).
- Branches : `feat/<id>-<slug>`, `fix/<id>-<slug>`, `docs/<slug>`.
- Chaque tâche se termine par une mise à jour du présent README (DEC-ST-013).
- Aucun secret dans Git ; `.env` ignoré.
- Aucune fonctionnalité déclarée terminée sans test exécuté et preuve AIDF.

*Statut : `DISPONIBLE` — Pint et PHPUnit activés et vérifiés le 2026-11-04.*

---

## 17. Limites connues

| Limite | Statut |
|---|---|
| Fonctionnalités métier | non implémentées — authentification et isolation disponibles (T-004 → T-006) |
| Frontend (Vite/npm) | non installé — livré avec T-011 |
| Conformité RGPD | **NOT_PROVEN** — analyse juridique requise |
| Playwright sous Windows | non vérifié (IN-07) |
| Trames officielles de rapports | indisponibles — modèles configurables |
| Version applicable du référentiel DWWM | à vérifier auprès de la source officielle |
| Antivirus sur les uploads | non prévu en V1 (documenté) |
| Chiffrement des sauvegardes | non prévu en V1 (documenté) |

*Statut : `DISPONIBLE` — mis à jour à chaque release.*

---

## Licence et réutilisation

Le mode de licence n'est pas encore décidé. Toute réutilisation par des
camarades de formation doit rester conforme aux règles de confidentialité des
données de stage (aucune donnée réelle partagée).
