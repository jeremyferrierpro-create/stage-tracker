# Audit fonctionnel et technique — Stage Tracker

- **Date** : 2026-11-19
- **Périmètre** : application `stage-tracker` (branche `main`, commit `c4e2508`, release 1.6.0)
- **Objet** : vérifier que la chaîne de valeur annoncée est réellement fonctionnelle et correctement implémentée ; identifier les manques et proposer un plan de remédiation.
- **Méthode** : inspection statique du code, des routes, des vues et des migrations ; exécution de la suite automatisée ; parcours E2E réel de la génération de rapport ; vérification des prérequis d'installation.

> **Verdict** : le socle est solide (176 tests, 697 assertions, 11 parcours Playwright, isolation multi-tenant et sécurité vérifiées). La **génération de rapports est bien implémentée et fonctionne de bout en bout**, mais elle est **inutilisable telle quelle sur l'environnement de démonstration** : aucune trame n'est créée par le seeder, aucun worker de file n'est documenté, un lien de navigation affiche encore « à venir », et l'export DOCX dépend d'une extension PHP non déclarée. Par ailleurs, deux maillons de la chaîne de valeur sont incomplets : **Commit → Fichier** (GitHub réel) et **Synthèse** (le rapport reste un inventaire de données, non une synthèse adaptée au mémoire / dossier professionnel / soutenance).

---

## 1. Ce qui a été vérifié

### 1.1 Environnement

| Élément | Valeur |
|---|---|
| PHP | 8.5.12 NTS (Laragon) |
| Laravel | 13.40.0 |
| PostgreSQL | 16.15 (conteneur `stage-tracker-postgres`) |
| Node / Playwright | 24.21.0 / 1.65.1 (Chromium 1255) |
| Base démo | `stage_tracker` (comptes de démonstration) |
| Base de test | `stage_tracker_test` |

**Anomalie d'environnement rencontrée et corrigée** : les extensions `pdo_pgsql` et `zip` étaient commentées dans `php.ini`, ce qui faisait échouer **les 176 tests** (`could not find driver`) et l'export DOCX (`Class "ZipArchive" not found`). Elles ont été réactivées (sauvegarde : `php.ini.bak-audit-20261118`). Docker Desktop s'était également arrêté, provoquant un blocage de la suite. Ces deux points sont externes au dépôt mais expliquent une perception de « rien ne fonctionne ».

### 1.2 Suite automatisée

| Vérification | Résultat |
|---|---|
| `php artisan test` | **176 tests PASS / 697 assertions** (après restauration des extensions) |
| `vendor/bin/pint --test` | **PASS** |
| `npx playwright test` | **PASS** : 10 parcours de régression + 1 scénario temporaire d'audit (génération de rapport, non conservé dans `e2e/`) |
| Exports PDF / DOCX | HTTP 200, fichiers non vides (PDF ≈ 4,8 Ko, DOCX ≈ 8,9 Ko) sur le rapport généré pendant l'audit |

### 1.3 Parcours réel de génération de rapport (preuve)

Le parcours suivant a été exécuté dans un navigateur réel, sans manipulation de base :

1. Connexion administrateur → espace de démonstration.
2. **Modèles de rapports** → *Nouveau modèle* (`Trame de synthèse audit`, type `memoir`) → création du brouillon.
3. Ajout d'une version `v1.0.0` (toutes les sections) → **publication** (version immuable).
4. Fiche du stage → **Rapports** → sélection de la trame → **Générer (asynchrone)** → statut **« En file »**.
5. Exécution du worker (`php artisan queue:work --stop-when-empty`) → statut **« Généré »**.
6. Téléchargement **HTML**, **PDF** et **DOCX** : 3 réponses HTTP 200, contenus non vides.

Conclusion : **le code de génération est correct**. Les causes de la perception « non implémenté » sont décrites en AUD-01 à AUD-05.

---

## 2. Conformité à la chaîne de valeur annoncée

| Maillon | Statut | Commentaire |
|---|---|---|
| Travail réalisé (journal) | ✅ | Création, clôture, notes privées, liaison aux activités ; export journal filtré. |
| Activité | ✅ | Projets, activités, statuts, objectifs/résultats, preuves et commits liés. |
| Tâche | ◐ | Modèle et endpoints complets, mais l'interface « Kanban » est un tableau et l'édition est limitée au statut (AUD-09). |
| Commit | ✅ | Connecteur GitHub, import idempotent, liaison aux activités, traçabilité `sync_runs`. |
| Fichier | ✖ | **Le connecteur GitHub renvoie toujours `files => []`** : aucun fichier n'est importé depuis un dépôt réel (AUD-07). |
| Preuve | ✅ | Fichier ou lien externe, types, visibilité `private/stage/shared`, archivage, liaison/déliaison aux activités, téléchargement contrôlé. |
| Compétence | ✅ | Compétences définies par espace, association aux activités, statuts mobilisée/documentée/évaluée. |
| Évaluation | ✅ | Réservée aux superviseurs/tuteurs habilités, appréciation validée = seul chemin vers `evaluated` (INV-09). |
| Synthèse | ◐ | Génération et exports opérationnels, mais contenu partiel et non différencié par type (AUD-08). |

---

## 3. Constats détaillés

### 3.1 Bloquants / critiques

#### AUD-01 — Aucune trame de rapport dans le jeu de démonstration
- **Constat** : `DemoSeeder` ne crée aucun `ReportTemplate`. Dans la démo, la page Rapports affiche « Aucune trame publiée : demandez à un administrateur… » et **le formulaire de génération est masqué**.
- **Preuve** : `database/seeders/DemoSeeder.php` (aucune occurrence de `ReportTemplate`) ; `resources/views/internships/reports.blade.php:44` ; comptage initial en base : `templates=0`.
- **Impact** : un utilisateur qui suit le README ne peut pas générer de rapport sans deviner qu'il doit d'abord créer une trame d'administration. C'est la première cause du signalement « la génération n'a pas été implémentée ».
- **Recommandation** : créer dans le seeder une trame publiée prête à l'emploi (ex. `Synthèse de stage` avec les 5 sections).

#### AUD-02 — File d'attente `database` sans worker documenté
- **Constat** : `QUEUE_CONNECTION=database` ; le README ne mentionne nulle part `php artisan queue:work`. Sans worker, un rapport reste indéfiniment au statut **« En file »**.
- **Preuve** : `.env` / `.env.example` ; `README.md` §5.3 et §12.2 ; `app/Http/Controllers/ReportController.php` (`GenerateReport::dispatch`) ; `app/Jobs/GenerateReport.php`.
- **Impact** : deuxième cause du signalement ; le bouton « Générer » semble ne rien faire.
- **Recommandation** : documenter le worker dans le démarrage (ou fournir un script `composer run dev` qui le lance) et ajouter un garde-fou : message d'aide explicite si `QUEUE_CONNECTION=database`, plus une commande de diagnostic.

#### AUD-03 — Lien de navigation « Rapports — à venir » alors que la fonctionnalité est livrée
- **Constat** : la barre latérale affiche `Rapports — à venir` en texte non cliquable, alors que les rapports par stage existent.
- **Preuve** : `resources/views/layouts/app.blade.php:126-127` ; `resources/views/internships/show.blade.php:44`.
- **Impact** : contredit le README §12 et renforce l'impression que la fonctionnalité manque.
- **Recommandation** : soit supprimer ce placeholder, soit créer une page transverse « Rapports » listant les rapports de tous les stages de l'espace (recommandé).

#### AUD-04 — Documentation des rapports inexacte
- **Constat** :
  1. Le README annonce un statut `En attente` ; le code affiche **`En file`** (`Report::statusLabel()`).
  2. Le README §12.3 affirme que le rapport distingue « les faits documentés (activités, **commits**) » et « les appréciations de tiers (**commentaires, revues**, évaluations) ». Le job n'inclut **ni commits, ni commentaires, ni revues**.
- **Preuve** : `README.md:726` et `README.md:736` ; `app/Jobs/GenerateReport.php:95-104` (sections `identity`, `activities`, `daily_logs`, `evidences`, `competencies` uniquement).
- **Impact** : promesse produit non tenue ; l'utilisateur cherche des données absentes.
- **Recommandation** : corriger le README à court terme ; compléter le contenu des rapports à moyen terme (AUD-08).

#### AUD-05 — `ext-zip` absente des prérequis et de `composer.json`
- **Constat** : l'export DOCX (PhpWord) exige `ZipArchive`. Cette extension n'est ni listée dans le README §3, ni déclarée dans `composer.json`. Sur une installation conforme au README, l'export DOCX renvoie une erreur 500.
- **Preuve** : `README.md:99` ; `composer.json` (aucun `ext-*`) ; échec observé de `ReportExportTest::test_export_docx_stores_and_downloads_a_valid_document` tant que `zip` était désactivée.
- **Impact** : fonctionnalité vendue « HTML, PDF, DOCX » non reproductible sur une installation neuve.
- **Recommandation** : ajouter `ext-zip` (et `ext-gd`, utilisée par dompdf) aux prérequis et à `composer.json` ; ajouter un test de prérequis.

#### AUD-06 — Aucun changement ni réinitialisation de mot de passe, aucun profil
- **Constat** : les routes se limitent à `login`, `logout` et aux invitations. Il n'existe ni page profil, ni changement de mot de passe, ni « mot de passe oublié », ni réinitialisation par un administrateur.
- **Preuve** : `routes/web.php` (aucune occurrence de `password`, `profile`, `reset`) ; `README.md` §5.1 (comptes créés par un administrateur).
- **Impact** : en production, un utilisateur ne peut pas remplacer son mot de passe provisoire ; en cas d'oubli, seul un accès direct à la base / au serveur permet de le débloquer. C'est un manque fonctionnel et un risque de sécurité.
- **Recommandation** : page « Mon compte » (changement avec mot de passe actuel) + réinitialisation administrateur (mot de passe temporaire à changer à la première connexion) ; à défaut, flux « mot de passe oublié » par e-mail.

### 3.2 Manques fonctionnels majeurs

#### AUD-07 — Le connecteur GitHub n'importe pas les fichiers des commits
- **Constat** : `GitHubProvider::fetchCommits()` construit chaque commit avec `'files' => []`. Les tables `commit_files` et l'affichage « Fichiers » existent, et le seeder les remplit, mais un dépôt réel n'en produit jamais.
- **Preuve** : `app/Integrations/GitHub/GitHubProvider.php:77` ; `app/Domain/Git/Services/CommitImportService.php:114` (import conditionné à `! empty($data['files'])`) ; `resources/views/repositories/show.blade.php:92`.
- **Impact** : le maillon **Commit → Fichier** de la chaîne de valeur est vide en conditions réelles ; la recherche/traçabilité par fichier est inopérante.
- **Recommandation** : récupérer les fichiers via `GET /repos/{owner}/{repo}/commits/{sha}` (ou GraphQL), avec gestion du quota (N+1 requêtes), stockage idempotent déjà en place, et tests `Http::fake`.

#### AUD-08 — Le rapport n'est pas une synthèse et le type de trame est décoratif
- **Constat** :
  - Sections disponibles : `identity`, `activities`, `daily_logs`, `evidences`, `competencies`.
  - Absents : **tâches**, **commits et fichiers**, **URL des preuves**, **commentaires/revues**, **historique des appréciations** (seule la dernière est reprise).
  - Le type de trame (`memoir`, `professional_file`, `oral_presentation`, `weekly_followup`…) n'influence **pas** le contenu généré : seules les sections cochées comptent.
  - Le rendu est un tableau HTML/CSS minimal, sans page de garde, sommaire, en-têtes/pieds ni numérotation.
- **Preuve** : `app/Domain/Reporting/Models/ReportTemplate.php` (`TYPES`, `ALLOWED_SECTIONS`) ; `app/Jobs/GenerateReport.php:95-104` ; rapport de démonstration ≈ 2,7 Ko.
- **Impact** : l'objectif « produire des synthèses pour son mémoire, son dossier professionnel et sa présentation orale » n'est pas réellement couvert. Les trois livrables sont identiques.
- **Recommandation** : introduire des **trames types** (structure et sections par défaut) et enrichir le contenu (tâches, commits, preuves avec liens, synthèse par compétence, faits marquants, conclusion). Ajouter une page de garde et un sommaire.

#### AUD-09 — « Kanban » et édition des tâches incomplets
- **Constat** : l'interface « Tâches (Kanban) » est un tableau avec un menu de statut, sans colonnes ni glisser-déposer. La création ne propose que titre, priorité et statut ; `description`, `due_date`, `acceptance_criteria` et `position` sont acceptés par le contrôleur mais jamais saisis. Aucun formulaire d'édition (titre, priorité, échéance) n'est exposé.
- **Preuve** : `resources/views/activities/show.blade.php:44-107` ; `app/Http/Controllers/TaskController.php` (règles `store`/`update`).
- **Impact** : la fonction annoncée comme « Kanban » n'en est pas un ; les métadonnées utiles (échéance, critères d'acceptation) sont inaccessibles.
- **Recommandation** : vraie vue en colonnes par statut (déplacement par boutons ou glisser-déposer accessible), formulaire d'édition complet, affichage des échéances et critères.

#### AUD-10 — Jeu de démonstration incomplet
- **Constat** : le seeder ne crée **ni tâche, ni commentaire, ni revue, ni évaluation, ni trame/rapport**. Il crée en revanche 2 compétences, 2 activités, 2 preuves (partagée/privée), 1 journal, 1 dépôt, 3 commits et des associations commit/compétence.
- **Preuve** : `database/seeders/DemoSeeder.php` (aucune occurrence de `Task`, `Comment`, `Review`, `Assessment`, `ReportTemplate`).
- **Impact** : à la première ouverture, les fonctionnalités phares (Kanban, collaboration, évaluation, synthèse) paraissent absentes.
- **Recommandation** : compléter le seeder pour démontrer toute la chaîne, y compris une trame publiée.

### 3.3 Améliorations de robustesse et d'exploitation

#### AUD-11 — Pagination et recherche quasi absentes
- **Constat** : seul le journal d'audit pagine (`paginate(50)`). Les listes de preuves, stages, activités, commits et rapports chargent l'intégralité des données.
- **Impact** : dégradation progressive avec le volume ; pas de recherche par titre, date, compétence ou fichier.
- **Recommandation** : pagination + filtres (statut, période, compétence, visibilité) et recherche plein texte sur les preuves/commits.

#### AUD-12 — Synchronisation GitHub synchrone et limitée
- **Constat** : `RepositoryController::sync()` appelle l'import dans la requête HTTP ; l'import est plafonné à `maxPages = 3` × 30 commits (90 derniers), sans curseur `since` ni reprise. Chaque requête HTTP a un timeout de 15 s.
- **Preuve** : `app/Http/Controllers/RepositoryController.php` (sync) ; `app/Domain/Git/Services/CommitImportService.php:20,27`.
- **Impact** : requête potentiellement longue, historique incomplet, pas de synchronisation incrémentale.
- **Recommandation** : job asynchrone, import incrémental (`since = last_synced_at`), pagination jusqu'à épuisement avec garde-fou de quota.

#### AUD-13 — Rapports en échec : aucune reprise ni diagnostic
- **Constat** : un rapport `failed` s'affiche « Échec » sans détail ni action (pas de relance, pas de suppression, pas de régénération). Les rapports générés ne peuvent pas être supprimés.
- **Impact** : impasse opérationnelle en cas d'erreur transitoire (worker, stockage).
- **Recommandation** : bouton « Relancer », affichage de la cause, suppression/archivage, et journalisation visible.

#### AUD-14 — Invitations dépendantes du log et lien non exposé
- **Constat** : `MAIL_MAILER=log` (documenté) et l'interface n'affiche pas le lien d'invitation. L'administrateur doit lire `storage/logs/laravel.log` pour onboarding.
- **Impact** : friction en démonstration et en recette ; risque d'erreur.
- **Recommandation** : configuration SMTP documentée, et en environnement local, affichage/copie du lien d'invitation après création (avec avertissement).

#### AUD-15 — Tableau de bord limité
- **Constat** : le tableau de bord agrège stages, compteurs d'activités et 5 derniers journaux. Aucune synthèse des preuves, compétences, commits ou rapports.
- **Impact** : le « tableau de bord consolidé » annoncé reste minimal.
- **Recommandation** : indicateurs (preuves partagées, compétences évaluées, commits liés, rapports générés), activité récente et alertes (échéances, rapports en échec).

#### AUD-16 — Sauvegardes non planifiées
- **Constat** : `app:backup` / `app:restore` existent et sont testés, mais aucune planification (scheduler/cron) ni politique de rétention n'est fournie.
- **Recommandation** : commande planifiée quotidienne, rotation, documentation de restauration d'urgence.

#### AUD-17 — Couverture de tests à compléter
- **Constat** : la génération et les exports sont couverts par des tests de fonctionnalité, mais aucun test E2E ne couvre le parcours trame → publication → génération → worker → téléchargement ; aucun test ne vérifie la présence de `ext-zip`.
- **Recommandation** : ajouter un scénario Playwright idempotent et un test de prérequis ; conserver les tests de non-régression sur les exclusions INV-14.

---

## 4. Plan de remédiation proposé

### Lot 0 — Correctifs de découvrabilité et d'exploitation (≈ 1 jour)
1. Seeder : trame publiée « Synthèse de stage » (5 sections).
2. README : worker de file au démarrage, statut `En file`, prérequis `ext-zip`/`ext-gd`, procédure de génération pas à pas.
3. `composer.json` : déclarer `ext-zip`, `ext-gd`.
4. Barre latérale : supprimer « Rapports — à venir » ou le remplacer par une page transverse.
5. Test E2E de génération de rapport (idempotent, environnement de test).

**Critères d'acceptation** : sur une base fraîchement seedée, un administrateur peut générer et télécharger HTML/PDF/DOCX sans étape non documentée ; `composer install` échoue explicitement si `ext-zip` manque ; aucun placeholder « à venir » pour une fonction livrée.

### Lot 1 — Maillon Commit → Fichier (≈ 2 jours)
1. Ajouter `fetchCommitFiles()` au provider GitHub (`/commits/{sha}`), avec tests `Http::fake`.
2. Importer les fichiers de façon idempotente (déjà supporté côté service).
3. Rendre la synchronisation asynchrone et incrémentale.

**Critères** : un commit GitHub réel importe ses fichiers (chemin, type de changement, ajouts/suppressions) ; une seconde synchronisation ne crée aucun doublon ; l'UI affiche les fichiers et permet la liaison à une activité.

### Lot 2 — Synthèses réellement adaptées (≈ 3 à 5 jours)
1. Sections supplémentaires : tâches, commits/fichiers, preuves (URL incluse), commentaires/revues, historique d'appréciations.
2. Synthèse par compétence (regroupement, niveau, preuves associées, appréciations).
3. Structures par type : mémoire (intro/objectifs/activités/synthèse compétences/conclusion), dossier professionnel (portfolio de preuves), soutenance (plan et messages clés).
4. Mise en page : page de garde, sommaire, en-têtes/pieds, numérotation.
5. Actions : relancer, supprimer, régénérer.

**Critères** : trois trames de types différents produisent trois structures distinctes ; le rapport inclut les commits et leurs fichiers ; les exclusions INV-14 restent prouvées par test.

### Lot 3 — Comptes et exploitation (≈ 2 jours)
1. Page « Mon compte » : changement de mot de passe (mot de passe actuel exigé).
2. Réinitialisation administrateur (mot de passe temporaire, changement forcé) ou flux e-mail.
3. SMTP documenté + lien d'invitation copiable en local.
4. Worker de file et sauvegarde planifiée (service/superviseur + ordonnanceur).

**Critères** : un utilisateur peut changer son mot de passe sans intervention en base ; un administrateur peut débloquer un compte ; une sauvegarde quotidienne est planifiée et testée.

### Lot 4 — UX et montée en charge (≈ 3 jours)
1. Vrai Kanban (colonnes, déplacement accessible, édition complète des tâches).
2. Pagination, recherche et filtres sur les listes principales.
3. Tableau de bord enrichi (preuves, compétences, commits, rapports, alertes).
4. Page transverse « Rapports ».

---

## 5. Annexes

### 5.1 Commandes exécutées

```powershell
php artisan test
vendor\bin\pint --test
cmd /c "npx playwright test --reporter=list"
php artisan queue:work --stop-when-empty
```

### 5.2 Preuves conservées

- `docs/screenshots/audit-report-generated.png` : rapport généré et statut « Généré » dans l'interface.
- Rapport HTML de démonstration : `storage/app/private/reports/01a1f278-6092-7066-9eea-3a054c596a2b.html` (créé pendant l'audit, conservé pour visualisation).
- Trame de démonstration conservée en base : `Trame de synthèse audit` (publiée, `v1.0.0`). **Elle n'est pas créée par le seeder** : c'est un artefact d'audit, à remplacer par AUD-01.

### 5.3 Limites de l'audit

- Pas d'audit manuel avec lecteur d'écran (l'audit automatisé axe-core reste la référence).
- Pas de test contre un dépôt GitHub réel (jeton non fourni) : le constat AUD-07 est établi par lecture du connecteur et par les tests avec provider simulé.
- Pas d'analyse juridique RGPD (déjà `NOT_PROVEN` dans la release).
- Les performances n'ont pas été mesurées sous charge ; les constats AUD-11/AUD-12 sont structurels.
