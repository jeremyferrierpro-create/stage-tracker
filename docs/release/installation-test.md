# Journal du test réel d'installation — T-072

*Statut : `DISPONIBLE` — exécuté le 2026-11-18 sur Windows 11, PowerShell.*

Objectif (T-072) : vérifier qu'un utilisateur peut installer et utiliser
Stage Tracker **uniquement en suivant le README**, depuis un clone vierge.

## Environnement du test

- Répertoire : `C:\Users\jerem\AppData\Local\Temp\opencode\stage-tracker-install`
  (hors dépôt de travail, installation « vierge »).
- Source : clone local du dépôt au commit `5be9c5a` (rapport de release 1.6.0).
- Base de données dédiée : `stage_tracker_install` (PostgreSQL 16,
  conteneur Docker `stage-tracker-postgres`), créée avec `psql` comme
  indiqué au §16.2 du README (variante de la commande §16.2).
- Port HTTP : 8010 (8000 déjà occupé par l'environnement de développement).

## Étapes exécutées (mesures réelles)

| # | Étape (référence README) | Résultat | Durée |
|---|---|---|---|
| 1 | `git clone` (§4.1) | 27 entrées racine, commit `5be9c5a` | < 5 s |
| 2 | `composer install` (§4.2) | dépendances installées sans erreur | 49,7 s |
| 3 | `cp .env.example .env` + `DB_DATABASE` / `DB_PASSWORD` + `php artisan key:generate` (§4.3) | `APP_KEY` générée ; `DB_DATABASE=stage_tracker_install` | < 5 s |
| 4 | PostgreSQL (§4.4) | conteneur déjà actif (`docker compose up -d`) | 0 s |
| 5 | `php artisan migrate` (§4.5) | 29 migrations exécutées | 4 s (avec seed) |
| 6 | `php artisan db:seed` (§4.5, optionnel) | `DemoSeeder … 1,038 ms DONE` | (inclus) |
| 7 | `npm install` + `npm run build` (§4.6) | `public/build/manifest.json` créé (app CSS 5,63 ko, JS 0,13 ko) | 3,6 s |
| 8 | `php artisan serve --port=8010` | application servie | — |

## Écarts constatés pendant le test → corrections README

| Écart constaté | Correction |
|---|---|
| Le §4.5 listait encore les tables supprimées par la simplification 1.6.0 (organisations, formations, référentiels) | §4.5 réécrit avec la liste réelle des 29 tables du périmètre simplifié |
| `npm install` échoue sous PowerShell (`npm.ps1` bloqué par la stratégie d'exécution) | entrée ajoutée au dépannage (§15) : exécuter via `cmd /c "npm …"` ou `npm.cmd` |

Aucun autre écart : toutes les commandes du README ont fonctionné telles quelles
(les difficultés PowerShell sur `npx`/`npm` étaient déjà documentées §15/§16.4).

## Parcours vérifiés (comptes de démonstration §4.5)

Script Playwright `e2e/installation-check.spec.ts` exécuté **depuis
l'installation fraîche**, contre `http://127.0.0.1:8010` :

| Compte | Parcours | Résultat |
|---|---|---|
| `admin@example.test` | connexion → ouvrir l'espace → tableau de bord → page Compétences (C1.1 visible) | PASS |
| `stagiaire@example.test` | connexion → espace → stages → fiche du stage → journal du stage | PASS |
| `maitre-de-stage@example.test` | connexion → espace → stages → fiche → matrice des compétences (C1.1) | PASS |
| `tuteur@example.test` | connexion → espace → tableau de bord | PASS |

Résultat Playwright : **1 passed** (4 comptes, parcours complets, 6,7 s).

Note de méthode : une première tentative avec `Invoke-WebRequest`
(script, pas navigateur) a produit des erreurs `HTTP 419` (jeton CSRF) ;
c'est une limite du client de script, pas de l'application — la connexion
réelle via navigateur (Playwright) fonctionne dans les 4 cas.

## Conclusion

Un camarade peut installer et utiliser l'outil en suivant uniquement le
README : **critère d'acceptation T-072 atteint**. Les deux écarts trouvés
ont été corrigés dans le README dans le même commit que ce journal.
