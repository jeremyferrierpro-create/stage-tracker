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
18. [Déploiement Cloud (Render & Vercel)](#18-déploiement-cloud-render--vercel)

---

## 1. Présentation

**Stage Tracker** est une application web de suivi de stages et de traçabilité
des activités professionnelles. Elle permet à un stagiaire de :

- consigner ses journées de travail ;
- organiser ses activités techniques et ses tâches (Kanban) ;
- relier ses commits Git à ses activités ;
- conserver des preuves (captures, documents, rapports de tests, liens) ;
- associer ses réalisations aux compétences définies par son espace de travail ;
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
| Phase AIDF | **Cycle complet** : 8 phases, 16 gates PASS (release 1.6.0 approuvée le 2026-11-18) |
| Code applicatif | socle Laravel 13.40.0 + authentification + isolation + invitations + audit + squelette d'interface + compétences définies par espace + stages (stagiaires, participants, projets, activités) + tâches Kanban + journal quotidien (clôture) + tableau de bord consolidé + preuves et visibilité (stockage abstrait local) + connecteur GitHub + collaboration (commentaires immuables, revues d'activité) + permissions effectives et révocation de participants + vue dédiée maître de stage + guide tuteur (compétences : mobilisée, documentée, évaluée) + modèles de rapports et exports (HTML, PDF, DOCX) + sauvegarde/restauration avec empreintes SHA-256 |
| Base de données | PostgreSQL 16.15 (conteneur Docker `stage-tracker-postgres`) |
| Tests | `php artisan test` → 176 tests PASS (697 assertions) ; `pint --test` → PASS ; Playwright → 10/10 PASS (dont audits axe-core) ; campagnes MT-01/MT-02/MT-03/MT-04/MT-05/MT-10/MT-11/MT-12 |
| Sécurité | Argon2id, rate limiting 5/15 min (429), sessions en base, messages neutres, audit append-only (trigger PostgreSQL) |
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

*Statut : `DISPONIBLE` — vérifié le 2026-11-18 (release 1.6.0).*

---

## 3. Prérequis

Les versions suivantes ont été **réellement vérifiées** sur l'environnement de
développement du projet :

| Outil | Version vérifiée | Vérification |
|---|---|---|
| PHP | 8.5.12 (NTS, VS17 x64) | `php -v` |
| Extensions PHP | `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `ctype`, `curl`, `fileinfo`, `tokenizer`, `xml`, `bcmath`, `gd`, `zip` | `php -m` |
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

> **Backend vérifié le 2026-11-04** (T-001 → T-003). Le frontend est livré
> depuis T-011 (vérifié le 2026-11-08).

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

Exécuté : 29 migrations, tables `users`, `sessions`, `cache`, `jobs`,
`workspaces`, `workspace_members`, `invitations`, `audit_logs`,
`competencies`, `internships`, `internship_participants`, `projects`,
`activities`, `tasks`, `daily_logs`, `daily_log_activities`, `evidences`,
`activity_evidences`, `repositories`, `commits`, `commit_files`,
`activity_commits`, `sync_runs`, `comments`, `reviews`,
`activity_competencies`, `competency_assessments`, `report_templates`,
`report_template_versions` et `reports` créées (T-004 → T-063 ;
périmètre simplifié 1.6.0 : plus de tables formations, organisations
d'accueil ou référentiels versionnés).

> Les seeders sont réservés aux environnements `local` et `test` ; ils ne
> contiennent aucune donnée réelle. Jeu de démonstration (optionnel, à la fin) :
>
> ```bash
> php artisan db:seed
> ```
>
> Crée « Espace Démo » avec 4 comptes de démonstration (`admin@example.test`,
> `stagiaire@example.test`, `maitre-de-stage@example.test`, `tuteur@example.test`,
> mot de passe `mot-de-passe-demo-2026`) et un jeu de compétences neutres ; y compris
> un stage, un projet démo, deux preuves (une partagée, une privée) et un dépôt
> Git démo avec 3 commits dont un lié à l'activité maquette (§11.5).
> Le seeder refuse de tourner en dehors de `local`/`testing` et est idempotent
> (T-010, vérifié le 2026-11-08).

### 4.6 Construire les assets frontend

```bash
npm install     # vite 6.4.5 + laravel-vite-plugin 1.3.0
npm run build   # génère public/build/manifest.json + assets hachés
```

- `npm run dev` sert les assets avec rechargement automatique pendant le
  développement (Vite tourne alors sur `http://localhost:5173`).
- Le layout utilise le build Vite quand `public/build/manifest.json` existe ;
  en absence de build (ex. suite de tests PHPUnit sans Node), une feuille de
  style de secours `public/css/app.css` est servie pour que l'application
  reste utilisable.

### 4.7 Vérifier que le socle fonctionne

```bash
php artisan --version       # Laravel Framework 13.40.0
php artisan db:show         # PostgreSQL 16.15, connexion pgsql
```

*Statut : `DISPONIBLE` — backend vérifié le 2026-11-04, frontend vérifié le 2026-11-08.*

### 4.8 Déployer sur Neon (optionnel)

Le projet est préconfiguré pour Neon (`neon.ts`, `.neon` ignoré par Git).
Neon fournit Postgres, et les services Auth, Functions, AI Gateway et Realtime.

```bash
npm i -g neon@latest
neon login
neon link --project-id <project-id> --branch production
npm i @neon/config
neon deploy
```

`neon deploy` provisionne les services déclarés et écrit les variables Neon
dans `.env` (`DATABASE_URL` poolée, `DATABASE_URL_UNPOOLED` directe, etc.).
Laravel utilise `DB_URL` (poolée) pour l'application ; les migrations passent
par la connexion directe :

```bash
php artisan migrate --database=pgsql_direct
```

> Le connecteur GitHub, l'authentification Laravel et le stockage local restent
> inchangés : Neon Auth / Object Storage ne remplacent pas les composants
> existants. L'AI Gateway nécessite des crédits sur le compte Neon.

Ne jamais committer `.env` ni `.neon`.

---

## 5. Premier lancement

> **Disponible** depuis T-004 → T-009 (vérifié le 2026-11-04).

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
4. À l'intérieur d'un espace (T-011) :
   - la barre supérieure porte le nom du produit, « Mes espaces », votre nom et
     la déconnexion ;
   - la navigation latérale regroupe « Tableau de bord », « Stages » (avec ses
     projets et activités), « Preuves », « Rapports », puis, uniquement pour les
     propriétaires et administrateurs, « Membres et invitations », « Compétences »,
     « Modèles de rapports » et « Journal d'audit » ;
   - le sélecteur « Espace actif » liste tous vos espaces avec leur rôle et
     permet de basculer directement vers leur tableau de bord ;
   - un fil d'Ariane rappelle l'emplacement (Mes espaces › Espace › Page) ;
   - un lien « Aller au contenu » (premier élément au clavier avec Tab) saute
     directement au contenu principal.
5. Sur mobile (< 900 px), la navigation latérale se replie derrière un bouton
   « ☰ Menu ». La navigation complète au clavier fait l'objet d'un audit
   d'accessibilité (T-066, §17 et rapport `docs/accessibility/`).

### 5.5 Sécurité de connexion

- Mots de passe hachés avec Argon2id.
- 5 échecs en 15 minutes → réponse HTTP 429 avec `Retry-After`.
- Message d'erreur identique pour un compte inconnu, un mot de passe faux ou un
  compte désactivé (aucune fuite d'existence).
- Sessions stockées en base ; identifiant de session régénéré à la connexion.
- `last_login_at` mis à jour à chaque connexion réussie.
- Connexion, déconnexion, échecs et blocages enregistrés dans le journal
  d'audit persistant (§10.6).

*Statut : `DISPONIBLE` — vérifié le 2026-11-04.*

---

## 6. Concepts clés

> Les concepts **workspace**, **rôles**, **stages**, **compétences** sont
> visibles dans l'interface (§7 à §10). Les captures d'écran seront enrichies
> à la fin du projet (audit accessibilité T-066).

Définitions déjà stabilisées par la spécification :

| Concept | Définition |
|---|---|
| **Workspace** | espace de travail isolé (personnel, associatif, professionnel, pédagogique) |
| **Stage** | période de stage rattachée à un workspace, un stagiaire et une entreprise d'accueil (champ texte) (T-023) |
| **Projet** | travail mené pendant le stage, contenant des activités (T-024) |
| **Activité** | unité de travail documentée (fonctionnalité, correction, étude, conception, recherche, maintenance, amélioration) avec objectifs, résultats, difficultés et solutions (T-025) |
| **Preuve** | élément vérifiable (capture, document, commit, lien) avec une visibilité contrôlée : privée, stage ou partagée (T-041) |
| **Tâche** | élément du Kanban rattaché à une activité, avec statut et position (T-026) |
| **Journal** | saisie quotidienne des travaux, difficultés, solutions et résultats, clôturable et révisable avant clôture (T-027/T-028) |
| **Compétence** | élément défini directement par les administrateurs de l'espace (code + intitulé) ; rattachable aux activités avec justification, puis évaluée nominativement par le maître de stage ou le tuteur (simplification 1.6.0) |
| **Rapport** | synthèse générée depuis un modèle versionné de l'espace, exportable en HTML, PDF ou DOCX (T-062 → T-064) |

*Statut : `DISPONIBLE` (définitions ; interfaces livrées).*

---

## 7. Guide du stagiaire

> **DISPONIBLE** — livré par T-023 → T-042 et T-060 (les invitations d'espace
> de travail sont déjà disponibles, voir §10.4).

### 7.1 Créer votre stage et inviter vos encadrants

Un propriétaire ou administrateur de l'espace crée le stage (§10.7) ; en tant
que stagiaire, vous êtes automatiquement participant. Vous pouvez modifier
votre propre stage (période, objectifs).

### 7.2 Travailler par projets et activités

Depuis la fiche du stage, créez vos projets puis vos activités (§10.8). Chaque
activité documente objectifs, résultats, difficultés et solutions ; le Kanban
suit son avancement (§10.9).

### 7.3 Saisir votre journal quotidien

Depuis la fiche du stage, « Journal » : une entrée par jour et par auteur
(horaires, durée, travaux, difficultés, solutions, résultats, reste à faire).
Vous la modifiez tant qu'elle est brouillon, puis vous la **clôturez** — elle
devient en lecture seule (§10.10).

### 7.4 Déposer des preuves

Le lien « Preuves » de l'espace : déposez un fichier (stocké hors racine web)
ou un lien externe, et choisissez la visibilité : `Privée` (vous seul), `Stage`
(vos participants) ou `Partagée`. Une preuve peut être liée à une activité
(§10.11).

### 7.5 Mobiliser des compétences

Sur une activité, la carte « Compétences » : choisissez une compétence de
l'espace et justifiez son rattachement (champ obligatoire). Les compétences
mobilisées apparaissent dans la matrice du stage, où le maître de stage ou le
tuteur peut les documenter puis les évaluer (§9).

### 7.6 Générer un rapport

Depuis la fiche du stage, « Rapports » : choisissez un modèle de l'espace et
lancez la génération (asynchrone), puis téléchargez le résultat en HTML, PDF
ou DOCX (§12).

*Statut : `DISPONIBLE`.*

---

## 8. Guide du maître de stage

> **DISPONIBLE** — livré par T-050 → T-054 (les invitations d'espace de travail sont
> déjà disponibles, voir §10.4).

### 8.1 Accepter une invitation et accéder à votre périmètre

1. Vous recevez un lien d'invitation valable 7 jours (§10.4). En le suivant, vous
   créez votre compte (ou vous connectez si le compte existe déjà) et rejoignez
   l'espace avec le rôle **maître de stage** ou **tuteur**.
2. L'administrateur (ou le stagiaire propriétaire) vous rattache ensuite à un stage
   précis via la fiche du stage (*Participants*).
3. Depuis la barre latérale, le lien **« Mes suivis »** (visible uniquement pour
   maître de stage / tuteur) ouvre votre vue dédiée `Mes suivis` :
   - les stages où vous êtes rattaché **actif** (et rien d'autre) ;
   - les revues d'activité qui vous sont assignées ;
   - les activités récentes du stage ;
   - les preuves de visibilité `stage` ou `shared` (jamais les preuves privées) ;
   - les dernières entrées du journal (travail réalisé, sans les notes personnelles).

### 8.2 Commenter une activité

Sur la fiche d'une activité (via *Stages* → projet → activité), la carte
**Commentaires** permet de publier un message avec un niveau de visibilité :

| Visibilité | Qui le voit |
|---|---|
| `partagé (shared)` | tous les membres de l'espace |
| `stage` | les membres rattachés au stage |
| `privé (private)` | vous seul (l'auteur) |

Un commentaire est **immuable** : ni son contenu, ni son auteur, ni sa date ne
peuvent être modifiés. Il peut être supprimé par son auteur ou par le gestionnaire
du stage. Les commentaires sont échappés automatiquement (aucun HTML n'est
interprété).

### 8.3 Demander et traiter une revue

- Le stagiaire (ou le gestionnaire du stage) **demande une revue** d'activité en
  choisissant un réviseur parmi les maîtres de stage / tuteurs rattachés actifs.
- Le **réviseur seul** peut enregistrer son feedback et faire évoluer le statut :
  `demandée` → `en cours` → `approuvée` ou `modifications demandées`. Une revue
  approuvée est un état final.
- Les revues à traiter apparaissent sur votre vue **« Mes suivis »**.
- Toutes les demandes et mises à jour sont tracées dans le journal d'audit.

### 8.4 Ce que vous ne pouvez PAS faire (garanties)

- Vous ne voyez **que les stages** où vous êtes rattaché actif : ni la liste
  complète des stages, ni les activités, ni le journal d'un autre stage.
- Les **preuves privées** du stagiaire ne sont jamais affichées (ni dans la liste,
  ni via leur URL directe : accès refusé 403).
- Les **notes personnelles** du journal du stagiaire ne sont jamais affichées.
- Vous ne pouvez **ni créer d'activité, ni créer d'entrée de journal, ni déposer
  de preuve** : ces actions restent au stagiaire et aux gestionnaires.
- Vous ne pouvez pas supprimer le commentaire d'un autre utilisateur.

### 8.5 Révocation et historique (administrateur)

Le gestionnaire d'un stage peut **révoquer** un participant (maître de stage,
tuteur ou observateur) depuis la fiche du stage : le participant perd l'accès
immédiatement mais **son historique est conservé** (commentaires, revues passées,
entrées d'audit). Aucune suppression en cascade. La révocation (comme la
réactivation) est journalisée dans l'audit. Le stagiaire lui-même ne peut pas être
révoqué dans cette version (transfert de stage non pris en charge, §17).

![Vue dédiée maître de stage](docs/screenshots/supervisor-dashboard.png)

*Statut : `DISPONIBLE`.*

---

## 9. Guide du tuteur pédagogique

> **DISPONIBLE** — livré par T-060, T-061.

En tant que tuteur (participant au stage, rôle `tutor` de l'espace), vous
consultez la matrice des compétences de chaque stage auquel vous êtes rattaché
(fiche stage → « Compétences »).

Trois états par compétence :

| État | Signification | Qui peut le poser |
|---|---|---|
| **Mobilisée** | la compétence est rattachée à une activité du stage, avec justification du stagiaire | stagiaire (ou administrateur) |
| **Documentée** | les preuves et journaux attestent la mise en œuvre ; le gestionnaire du stage le confirme | maître de stage / tuteur participant |
| **Évaluée** | vous (ou le maître de stage) portez une appréciation nominative (`validated` = validée, `remediation` = à consolider) avec un commentaire daté et signé | maître de stage / tuteur participant |

Garanties :

- **Aucune validation automatique** (INV-09) : ni la clôture d'une activité, ni
  la clôture d'une journée de journal ne valident une compétence. Seule une
  appréciation explicite d'un évaluateur habilité fait passer la compétence à
  « Évaluée » (AC-11.1, AC-11.2).
- Une compétence **évaluée ne peut pas être retirée** de l'activité : l'entrée
  d'historique est conservée (aucune destruction de trace).
- Le stagiaire **ne peut pas s'auto-évaluer** ; le propriétaire de l'espace
  n'évalue que s'il est participant du stage.
- Chaque évaluation est tracée dans le journal d'audit
  (`assessment.created`).

*Statut : `DISPONIBLE`.*

---

## 10. Guide de l'administrateur

> **DISPONIBLE** — base livrée par T-006, invitations (T-007), compétences
> (simplification 1.6.0) et journal d'audit (T-009) disponibles ; complété par
> T-021, T-062.

### 10.1 Rôles d'un espace de travail

| Rôle | Consulter | Modifier l'espace | Gérer les membres | Gérer les compétences | Consulter l'audit | Supprimer |
|---|---|---|---|---|---|---|
| `owner` | oui | oui | oui | oui | oui | oui |
| `admin` | oui | oui | oui | oui | oui | non |
| `trainee` | oui | non | non | non | non | non |
| `supervisor` | oui | non | non | non | non | non |
| `tutor` | oui | non | non | non | non | non |
| `viewer` | oui | non | non | non | non | non |

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

Page **Membres et invitations** (`/w/{workspace}/members`), réservée aux rôles
`owner` et `admin` :

1. Saisir l'adresse e-mail et choisir le rôle proposé.
2. Le lien d'invitation est envoyé par e-mail et expire après **7 jours**.
   En local, le mail est écrit dans `storage/logs/laravel.log` (`MAIL_MAILER=log`).
3. L'invité ouvre le lien puis :
   - s'il possède déjà un compte → il se connecte et accepte (ou refuse) ;
   - sinon → il crée son compte directement depuis la page d'invitation
     (mot de passe de 12 caractères minimum).
4. L'appartenance créée a **exactement** le rôle proposé (aucune élévation).

Règles de rôles proposables :

| Inviteur | Rôles proposables |
|---|---|
| `owner` | `admin`, `trainee`, `supervisor`, `tutor`, `viewer` |
| `admin` | `trainee`, `supervisor`, `tutor`, `viewer` (pas `owner` ni `admin`) |
| autre | aucun |

Autres règles :

- une seule invitation en attente par adresse et par espace ;
- le propriétaire de l'espace ne peut pas être changé par invitation ;
- l'invitation peut être annulée par un administrateur ;
- refus ou expiration → aucun accès créé, l'événement reste dans l'audit ;
- visiter le lien après acceptation → page « 410 Invitation indisponible » ;
- seul le hash SHA-256 du token est stocké en base.

### 10.5 Compétences (simplification 1.6.0)

Page **Compétences** (`/w/{workspace}/competencies`), réservée à `owner` et
`admin` : liste simple des compétences de l'espace (code + intitulé +
description optionnelle).

- Les compétences sont définies **directement** par les administrateurs de
  l'espace : il n'y a plus de référentiel versionné, de bloc ni de critère
  (décision de simplification 1.6.0).
- Le code doit être unique dans l'espace (ex. `C1.1`).
- Une compétence **associée à une activité** ne peut pas être supprimée :
  le bouton est masqué et la tentative est refusée (aucune perte de trace).
- La création, la modification et la suppression sont journalisées
  (`competency.created`, `competency.deleted`).
- Les compétences de cet espace sont ensuite rattachables aux activités des
  stages (§7.5) et évaluables (§9).

### 10.6 Journal d'audit

Page **Journal d'audit** (`/w/{workspace}/audit`), réservée à `owner` et
`admin`. Chaque entrée contient : horodatage, action, acteur (ou « Système »),
cible, métadonnées filtrées et IP.

Actions journalisées à ce stade : `auth.login`, `auth.logout`,
`auth.login.failed`, `auth.login.throttled`, `workspace.created`,
`workspace.access.denied` (403 membre non actif), `user.created`,
`invitation.created/accepted/declined/cancelled`,
`competency.created/deleted/associated/documented/unlinked`,
`assessment.created`, `internship.created/updated`, `project.*`,
`activity.*`, `task.*`, `daily_log.closed`, `evidence.*`, `commit.linked`,
`comment.created`, `review.*`, `report_template.*`, `report.generated`,
`repository.connected/synced`.

Garanties :

- **Append-only** : toute mise à jour ou suppression est refusée par le modèle
  **et** par un trigger PostgreSQL (même une requête directe en base échoue).
- Les métadonnées passent par une **liste blanche** de clés : aucun mot de
  passe, token ou secret ne peut être journalisé.
- L'adresse e-mail n'est jamais stockée en clair dans l'audit (uniquement son
  empreinte SHA-256).
- Les échecs de connexion sont rattachés à l'acteur si le compte existe, sans
  jamais contenir le mot de passe tenté.

*Statut : `DISPONIBLE` — rôles, création de comptes/espaces, invitations,
compétences et journal d'audit vérifiés (suite continue PHPUnit).*

### 10.7 Stages (T-023)

Dans un espace, le lien « Stages » liste les stages (titre, stagiaire,
entreprise d'accueil, période, statut). Propriétaires et administrateurs
peuvent créer un stage (stagiaire membre actif de l'espace, **entreprise
d'accueil** saisie en texte libre, dates, objectifs, statut). La fiche de
stage permet d'ajouter des participants (stagiaire, maître de stage, tuteur)
et d'accéder aux projets. Le stagiaire concerné peut aussi modifier son stage.

### 10.8 Projets et activités (T-024, T-025)

Depuis la fiche d'un stage, « Projets » liste et crée des projets (nom,
description, statut, période). La fiche d'un projet liste ses activités et
permet d'en créer de nouvelles (titre, type, statut Kanban, priorité,
période, objectifs, résultats, difficultés, solutions). Chaque activité peut
être modifiée et archivée. Les activités sont rattachées à un seul projet ;
deux stages d'un même stagiaire restent strictement séparés (MT-04).

*Statut : `DISPONIBLE` (projets, activités) — vérifié le 2026-11-13.*

### 10.9 Tâches et Kanban (T-026)

Depuis la fiche d'une activité, la section « Tâches (Kanban) » liste les
tâches avec leur statut, priorité et échéance. Il est possible d'ajouter une
tâche (titre, priorité, statut) et de changer son statut via la liste
déroulante (sauvegarde immédiate). Les tâches peuvent être supprimées.
L'ordre est conservé par une position.

### 10.10 Journal quotidien (T-027, T-028)

Depuis la fiche d'un stage, le lien « Journal » liste les journées saisies
(date, auteur, durée, statut). Une journée contient les horaires, la durée,
les objectifs, les travaux réalisés, les difficultés, les solutions, les
résultats, le reste à faire et des notes privées. Il est possible de lier
des activités du stage à la journée avec une contribution. Une journée
brouillon peut être modifiée ; la clôture la rend en lecture seule.

*Statut : `DISPONIBLE` (tâches, Kanban, journal, clôture) — vérifié le 2026-11-13.*

### 10.11 Preuves et visibilité (T-040, T-041, T-042)

Dans un espace, le lien « Preuves » liste les preuves (titre, type,
visibilité, propriétaire). Il est possible de créer une preuve en
déposant un fichier (stocké hors webroot, clé de stockage en base) ou en
indiquant un lien externe, avec une visibilité `Privée`, `Stage` ou
`Partagée`. Une preuve peut être liée à une ou plusieurs activités
(contribution optionnelle). L'archivage conserve l'historique (aucune
suppression physique). Le stockage est abstrait (`EvidenceStorageInterface`,
implémentation locale) : une évolution vers un stockage distant ne change
pas le domaine.

*Statut : `DISPONIBLE` (preuves, visibilité, liaison activité) — vérifié le 2026-11-13.*

---

## 11. Connecteur GitHub

*Statut : `DISPONIBLE` (dépôts, import idempotent des commits, fichiers et
liaison aux activités) — vérifié le 2026-11-15.*

### 11.1 Associer un dépôt à un projet

Depuis la fiche d'un projet, cliquer sur **Dépôts Git** puis renseigner :

| Champ | Rôle |
|---|---|
| Fournisseur | `GitHub` (par défaut), `GitLab` ou `Autre` |
| Identifiant externe | `propriétaire/dépôt` — unique par fournisseur |
| Nom affiché | nom lisible dans l'application |
| URL du dépôt | ex. `https://github.com/mon-compte/mon-depot` |
| Branche par défaut | `main` par défaut |
| Jeton d'accès | **optionnel** — voir §11.2 |

Qui peut gérer : propriétaire, administrateur ou stagiaire du stage. Un
tuteur ou observateur peut consulter la fiche dépôt mais pas synchroniser
ni dissocier.

### 11.2 Jeton GitHub (sécurité)

- Recommandation : créer un *fine-grained personal access token* en
  **lecture seule** sur les dépôts concernés.
- Le jeton est **chiffré côté serveur** (Laravel `Crypt`, clé `APP_KEY`)
  et stocké dans la colonne `token_encrypted` de la table `repositories`.
- Il n'est **jamais** renvoyé au navigateur : l'attribut est masqué au
  sérialiseur du modèle (`$hidden`) et absent de toutes les vues.
- Il n'apparaît jamais dans le journal d'audit (liste blanche des
  métadonnées, T-009).
- Sans jeton, GitHub limite l'API à 60 requêtes/heure ; avec jeton,
  5 000/heure.

Le formulaire propose aussi un **tester de connexion** qui vérifie
l'accès au dépôt avant l'association (erreur claire en cas d'échec :
URL invalide, dépôt privé sans droits, jeton invalide ou limite d'API).

### 11.3 Synchroniser les commits

Sur la fiche du dépôt, **Synchroniser maintenant** importe jusqu'à
3 pages de 30 commits depuis la branche par défaut (connecteur GitHub
REST v3). Chaque exécution est tracée dans `sync_runs` :

| Statut | Signification |
|---|---|
| `success` | import complet |
| `partial` | import partiel (erreurs sur certains commits) |
| `failed` | exécution en échec (message d'erreur conservé) |

### 11.4 Import idempotent (INV-05)

- Unicité `(repository_id, sha)` : relancer une synchronisation
  **ne crée aucun doublon**.
- La date d'import (`imported_at`) d'un commit déjà présent n'est pas
  remontée — la provenance est conservée.
- Les associations commit ↔ activité déjà créées survivent aux
  synchronisations suivantes.
- Les fichiers modifiés par un commit (`commit_files`, unicité
  `(commit_id, path)`) sont mis à jour sans doublon.

### 11.5 Lier des commits à une activité

Sur la fiche du dépôt, chaque commit propose un sélecteur d'activités du
projet et un bouton **Lier**. Plusieurs commits peuvent être liés à une
même activité ; lier deux fois le même commit est sans effet (unicité
`activity_id + commit_id`). Le lien est interdit entre espaces de travail
différents (clé étrangère composite).

### 11.6 Dissocier un dépôt

**Dissocier** supprime le dépôt **et** ses commits importés (et les liens
d'activité) par cascade — les activités et preuves sont
conservées.

*Statut : `DISPONIBLE`.*

---

## 12. Rapports et exports

> **DISPONIBLE** — livré par T-062 → T-064.

### 12.1 Modèles de rapports

Les propriétaires et administrateurs de l'espace définissent des **modèles**
(page « Modèles de rapports ») : un modèle regroupe des sections parmi
`identity` (identité du stage), `activities` (activités), `daily_logs`
(journal), `evidences` (preuves) et `competencies` (compétences). Chaque
modèle possède des **versions** : la version publiée est immuable (INV-14),
toute évolution crée une nouvelle version.

### 12.2 Générer et télécharger

Depuis la fiche du stage, « Rapports » : choisissez un modèle de l'espace et
lancez la génération. Elle est **asynchrone** (file de tâches) : le statut
passe de `En file` à `Généré` (ou `Échoué`), puis le rapport est
téléchargeable en **HTML**, **PDF** ou **DOCX**.

> **Worker requis** : avec `QUEUE_CONNECTION=database`, lancez
> `php artisan queue:work` dans un second terminal. Sans worker, le rapport
> reste au statut `En file` et aucun fichier n'est produit.

### 12.3 Exclusions garanties

Le filtrage est effectué **côté serveur** (INV-14) :

- les **notes privées** des journal quotidien ne sont jamais exportées ;
- les **preuves non partagées** (privées) sont exclues, et la liste des
  exclusions est jointe au rapport ;
- le rapport distingue les **faits documentés** (activités, commits), les
  **déclarations du stagiaire** (journal) et les **appréciations de tiers**
  (commentaires, revues, évaluations).

*Statut : `DISPONIBLE` — vérifié le 2026-11-17.*

---

## 13. Sauvegarde et restauration

> **DISPONIBLE** — livré par T-065.

Deux commandes artisan (utilisables par l'exploitant du serveur) :

```bash
php artisan app:backup                    # sauvegarde complète
php artisan app:restore <chemin> --dry-run   # simulation
php artisan app:restore <chemin>          # restauration réelle
```

- La sauvegarde exporte **toutes les tables** en JSONL (un fichier par table),
  **copie les fichiers de preuves** du stockage privé, et écrit un
  `manifest.json` avec l'**empreinte SHA-256** de chaque fichier.
- La restauration vérifie les empreintes **avant** d'écrire (une archive
  corrompue est refusée sans aucune modification), insère les données dans
  l'**ordre topologique** des clés étrangères, recadre les séquences et
  restitue les fichiers à l'identique.
- `--dry-run` vérifie les empreintes et calcule le plan d'insertion **sans
  rien écrire**.
- Preuve MT-12 : sauvegarde puis restauration d'une base de démonstration
  complète (43 tables, 76 lignes, 75 fichiers) à l'identique.

*Statut : `DISPONIBLE`.*

---

## 14. Sécurité et confidentialité

> **Disponible** — revue de sécurité complète livrée (T-070), rapport dans
> `docs/security/` : aucune vulnérabilité bloquante ouverte.

Sections livrées :
- **trois niveaux de visibilité** des preuves (`private`, `stage`, `shared`) :
  une preuve privée n'est visible que de son propriétaire, jamais par le maître de
  stage (ni dans les listes, ni via son URL directe — 403) ;
- **permissions effectives** (T-052) : pour chaque stage, vos droits réels sont
  l'intersection de votre rôle dans l'espace, de votre rôle de participant au stage
  et de vos restrictions individuelles éventuelles (les permissions par participant
  ne peuvent que **restreindre**, jamais élargir, à l'exception de `export` qui
  peut être accordé à un maître de stage / tuteur) ;
- **révocation sans destruction** (T-053) : retirer un participant ne supprime
  jamais son historique ;
- **évaluations nominatives** (T-060/T-061) : seule une appréciation explicite
  d'un maître de stage ou tuteur habilité valide une compétence ; le stagiaire
  ne peut pas s'auto-évaluer et une compétence évaluée n'est jamais retirée
  silencieusement (§9) ;
- **rapports** (T-062 → T-064) : filtrage serveur des notes privées et des
  preuves non partagées, exclusions listées (§12) ;
- **jetons GitHub** : chiffrés au repos, jamais exposés (§11).

Sections prévues :
- bonnes pratiques sur les secrets (§15.3 : aucun secret dans `.env`
  committé, `.env.example` documenté, jeton GitHub chiffré au repos).

*Statut : `DISPONIBLE` (T-041, T-052, T-053, T-070) — revue de sécurité
exécutée le 2026-11-18 : les 8 tests de sécurité obligatoires sont PASS avec
preuve automatisée, checklist et limites documentées dans `docs/security/`.*

---

## 15. Dépannage

Faits déjà observés sur l'environnement de développement :

| Symptôme | Cause | Solution |
|---|---|---|
| `npm` ou `composer` refusé par la stratégie PowerShell | scripts `.ps1` bloqués | utiliser `npm.cmd` / `composer.bat` |
| `pdo_sqlite` absent | extension non installée | sans impact : la base cible est PostgreSQL (`pdo_pgsql` présent) |
| `psql` introuvable | PostgreSQL non installé nativement | utiliser Docker (`docker compose up -d`) |
| port 5432 déjà utilisé | instance locale existante | arrêter le service ou changer `DB_PORT` |
| `HTTP 419` après connexion | jeton CSRF expiré (page restée ouverte) | recharger la page de connexion et se reconnecter |
| Docker Desktop arrêté (déconnexion Postgres) | le moteur Docker ne tourne plus | relancer Docker Desktop puis `docker compose up -d` |
| `npm` bloque sous PowerShell (`npm.ps1`) | stratégie d'exécution de scripts | exécuter via `cmd /c "npm ..."` ou utiliser `npm.cmd` |

> Les erreurs propres à l'application seront ajoutées au fil des tâches, avec
> le message réel observé et la solution vérifiée.

*Statut : `DISPONIBLE` (environnement et application) — test réel
d'installation effectué le 2026-11-18 depuis un clone vierge (T-072,
journal dans `docs/release/installation-test.md`).*

---

## 16. Développement et contribution

### 16.1 Structure du dépôt

```
.aidf/            gouvernance AIDF, documents, preuves, workflow
docs/             architecture, base de données, fonctionnel, tests, planning
app/Console/      commandes artisan (comptes, espaces, sauvegarde/restauration)
app/Domain/Identity/  utilisateurs, workspaces, appartenances, invitations,
                  contexte courant
app/Domain/Internship/ stages, participants (T-023)
app/Domain/Project/   projets et activités (T-024/T-025)
app/Domain/Evaluation/ compétences, associations, évaluations (T-060/T-061)
app/Domain/Audit/ journal d'audit (modèle append-only, service d'écriture)
app/Domain/       autres domaines métier (Internship, Journal, Evidence,
                  Reporting, Collaboration, Git)
app/Http/         contrôleurs, middlewares, requêtes de formulaire
app/Mail/         e-mails transactionnels (invitation)
app/Integrations/ Git, GitHub, Storage
app/Models/Concerns/  traits transverses (BelongsToWorkspace)
app/Policies/     autorisations par ressource
bootstrap/        démarrage de l'application
config/           configuration
database/         migrations, factories, seeders
public/           point d'entrée web (index.php), css/app.css (secours sans npm),
                  build/ (assets Vite produits, ignoré par Git)
resources/        vues Blade, css/app.css (source), js/app.js
routes/           web.php (api.php à ajouter avec l'API)
storage/          logs, cache, fichiers privés
tests/            Feature, Support
vite.config.js    configuration des assets (vite 6.4.5)
package.json      dépendances Node de build
vendor/           dépendances PHP (ignoré par Git)
docker-compose.yml
.env.example
phpunit.xml
```

### 16.2 Commandes de qualité

```bash
vendor/bin/pint --test      # style — PASS le 2026-11-18
php artisan test            # tests PHPUnit - 175 tests PASS (694 assertions) le 2026-11-18
php artisan migrate:fresh --seed --env=testing   # jeu de démo neutre — vérifié le 2026-11-18
npm run build               # assets Vite — vérifié le 2026-11-08
npx playwright test         # tests navigateur — 10 tests PASS le 2026-11-18 (voir §16.4)
```

### 16.4 Tests navigateur (Playwright)

Parcours critiques vérifiés dans un vrai navigateur (Chromium) :

| Fichier | Parcours |
|---|---|
| `e2e/auth.spec.ts` | connexion / déconnexion, mot de passe erroné |
| `e2e/git-traceability.spec.ts` | MT-06 : du tableau de bord à la fiche dépôt, commits visibles et liaison activité |
| `e2e/evidence-visibility.spec.ts` | MT-07/MT-08 : preuve privée invisible et refusée (403) au maître de stage, preuve partagée consultable |
| `e2e/supervisor-view.spec.ts` | vue maître de stage : journal visible, notes privées masquées |
| `e2e/tutor-assessment.spec.ts` | guide tuteur (T-060/T-061) : évaluer une compétence, capture §9 |
| `e2e/accessibility.spec.ts` | audit accessibilité axe-core (T-066, `docs/accessibility/`) |

Prérequis (une fois) :

```bash
npm install                          # installe @playwright/test
npx playwright install chromium      # navigateur (~124 Mo)
```

Exécution :

```bash
php artisan migrate:fresh --seed     # jeu de démo (les tests s'appuient dessus)
php artisan serve --host=127.0.0.1 --port=8000   # dans un 2e terminal
npx playwright test                  # rapport HTML dans e2e-report/ (ignoré par Git)
npx playwright show-report           # consulter le rapport
```

Variable `E2E_BASE_URL` pour pointer vers une autre adresse si besoin.

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

*Statut : `DISPONIBLE` — Pint, PHPUnit, build Vite et Playwright (10 parcours, dont audit axe-core) activés et vérifiés le 2026-11-18.*

---

## 17. Limites connues

| Limite | Statut |
|---|---|
| Modules métier suite | livrés (évaluations T-060/T-061, rapports T-062 → T-064, sauvegarde T-065, accessibilité T-066, revue de sécurité T-070, release T-071, README final + test d'installation T-072) |
| Périmètre simplifié 1.6.0 | décision utilisateur : le module Formations, les Organisations d'accueil (remplacées par un champ texte « entreprise d'accueil » sur le stage) et les référentiels versionnés (blocs/critères) sont supprimés ; les compétences sont définies directement par espace |
| Navigation clavier complète | audit automatique axe-core livré en T-066 (10 parcours Playwright PASS, zéro défaut serious/critical, rapport `docs/accessibility/`) ; l'audit manuel (lecteur d'écran) reste un complément recommandé |
| Version Livewire | résolu — DEC-ST-014 accepté (Livewire 4.4.8 installé) ; l'interface reste en Blade pur à ce jour, aucun composant Livewire utilisé |
| Envoi réel d'e-mails | non configuré — `MAIL_MAILER=log` (le lien d'invitation va dans le log) |
| Suspension / changement de rôle des membres | livré par T-021 |
| Conformité RGPD | **NOT_PROVEN** — analyse juridique requise |
| Playwright sous Windows | vérifié le 2026-11-15 — 4 parcours PASS (Chromium headless shell 159, §16.4) ; l'audit accessibilité reste en T-066 |
| Trames officielles de rapports | indisponibles — modèles configurables par l'administrateur de l'espace |
| Transfert de stage (réaffectation du stagiaire) | non pris en charge — le stagiaire d'un stage ne peut pas être révoqué ; un nouveau stage est créé à la place (T-053) |
| Version applicable du référentiel | non applicable — référentiels supprimés en 1.6.0 (compétences définies par espace) |
| Antivirus sur les uploads | non prévu en V1 (documenté) |
| Chiffrement des sauvegardes | non prévu en V1 (documenté) |

*Statut : `DISPONIBLE` — mis à jour à chaque release.*

---

## 18. Déploiement Cloud (Render & Vercel)

Pour permettre aux 4 collègues de formation DWWM d'accéder à Stage Tracker en libre accès 24/7 sans dépendre d'une machine locale, le projet est prêt pour le déploiement sur **Render** (recommandé en conteneur Docker) ou **Vercel** (serverless), relié à la base managée **Neon PostgreSQL**.

### 18.1 Déploiement sur Render (Web Service Docker — Recommandé)

Render est la plateforme idéale pour héberger un monolithe Laravel avec PostgreSQL.

#### Étapes d'installation :
1. Créez un compte sur [Render.com](https://render.com).
2. Cliquez sur **New +** → **Web Service**.
3. Liez votre dépôt GitHub contenant le projet Stage Tracker (ou spécifiez l'URL du dépôt).
4. Définissez les options :
   - **Name** : `stage-tracker-dwwm`
   - **Region** : `Frankfurt (EU Central)` (pour être au plus près de l'instance Neon EU)
   - **Runtime** : `Docker` (utilise le fichier `Dockerfile` fourni à la racine)
   - **Instance Type** : `Free` ou `Starter` (0.5 CPU, 512 MB RAM)
5. **Variables d'environnement** à renseigner dans l'onglet *Environment* :
   - `APP_NAME` : `Stage Tracker DWWM`
   - `APP_ENV` : `production`
   - `APP_DEBUG` : `false`
   - `APP_KEY` : Clé 32 octets base64 (générez-la en local avec `php artisan key:generate --show`)
   - `APP_URL` : `https://stage-tracker-dwwm.onrender.com`
   - `DB_CONNECTION` : `pgsql`
   - `DATABASE_URL` : Votre chaîne de connexion Neon (ex: `postgresql://user:pass@ep-dark-morning-...eu-central-1.aws.neon.tech/neondb?sslmode=require`)
   - `SESSION_DRIVER` : `database`
   - `CACHE_STORE` : `database`
   - `QUEUE_CONNECTION` : `sync`
6. Cliquez sur **Deploy Web Service**.
   - Le build installe les dépendances PHP, compile les assets Vite (`npm run build`), applique les migrations automatiques (`php artisan migrate --force`), et démarre le serveur web.

---

### 18.2 Déploiement sur Vercel (Architecture Serverless)

Vercel permet d'exécuter Stage Tracker via le runtime serverless PHP (`vercel-php`).

#### Étapes d'installation :
1. Installez le CLI Vercel ou connectez-vous sur [Vercel.com](https://vercel.com) :
   ```bash
   npm i -g vercel
   vercel login
   ```
2. À la racine du projet, vérifiez la présence de `vercel.json` et du bridge `api/index.php` (déjà fournis).
3. Compilez les assets en amont :
   ```bash
   npm run build
   ```
4. Lancez le déploiement :
   ```bash
   vercel --prod
   ```
5. Dans le tableau de bord Vercel (**Settings** → **Environment Variables**), ajoutez les variables suivantes :
   - `APP_NAME` : `Stage Tracker DWWM`
   - `APP_ENV` : `production`
   - `APP_DEBUG` : `false`
   - `APP_KEY` : Votre clé Laravel (`base64:...`)
   - `APP_URL` : L'URL attribuée par Vercel (ex: `https://stage-tracker-dwwm.vercel.app`)
   - `DB_CONNECTION` : `pgsql`
   - `DATABASE_URL` : Chaîne de connexion Neon (`postgresql://...`)
   - `SESSION_DRIVER` : `cookie` (recommandé en serverless éphémère sans état local)
   - `CACHE_STORE` : `array`
   - `VIEW_COMPILED_PATH` : `/tmp/views`
6. Pour exécuter les migrations initiales sur la base Neon, lancez une fois en local :
   ```bash
   php artisan migrate --force
   ```

---

## Licence et réutilisation

Le mode de licence n'est pas encore décidé. Toute réutilisation par des
camarades de formation doit rester conforme aux règles de confidentialité des
données de stage (aucune donnée réelle partagée).
#   s t a g e - t r a c k e r  
 