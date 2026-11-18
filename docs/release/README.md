# Rapport de release 1.6.0 — T-071

*Statut : `DISPONIBLE` — rapport établi le 2026-11-18.*

## 1. Identité de la livraison

| Champ | Valeur |
|---|---|
| Produit | Stage Tracker — suivi de stage en entreprise, multi-utilisateurs |
| Version | **1.6.0** |
| Date | 2026-11-18 |
| Branche | `main` (dépôt local, pas de remote) |
| Dernier commit | `cbbdd3b` — `docs(security): approbation humaine de la revue, gate security-exit PASS` |
| Arbre | propre (seules les preuves de gate de la phase release restent à committer) |

## 2. Contenu livré (historique des commits clés)

| Commit | Contenu |
|---|---|
| `f17d81e` | T-050 → T-054 : commentaires immuables, revues, permissions effectives, révocation, vue maître de stage |
| `1a52603` | T-060/T-061 : compétences d'activité et évaluations nominatives (INV-09) |
| `38e7c90` | T-062 → T-064 : trames versionnées, génération asynchrone, exports PDF/DOCX (INV-14) |
| `a325ba8` | T-065 : sauvegarde/restauration JSONL + empreintes SHA-256 (MT-12) |
| `9578e41` | Simplification 1.6.0 (décision utilisateur) : suppression Formations / Organisations d'accueil / référentiels versionnés, compétences définies directement par espace, champ texte « entreprise d'accueil » |
| `508f16d` | T-066 : audit accessibilité axe-core, corrections labels, parcours guide tuteur |
| `0d0bfc3` | T-070 : revue de sécurité, campagne S-02, rapport `docs/security/` |

## 3. Checklist release

| Vérification | Statut | Preuve |
|---|---|---|
| Suite PHPUnit complète | PASS | 176 tests / 697 assertions (2026-11-18) |
| Style (Pint) | PASS | `vendor/bin/pint` sans erreur |
| Migrations | PASS | 29 migrations ; `migrate:fresh --seed` exécuté avec succès (PostgreSQL 16, conteneur `stage-tracker-postgres`) |
| Seed de démonstration | PASS | `DemoSeeder` : 4 comptes, espace « Espace Démo », stage « Stage Démo — Développeur Web », 2 activités, 2 compétences (C1.1, C2.1) |
| Parcours navigateur (Playwright) | PASS | 10/10 tests (Chromium, contre le serveur de démonstration réel) |
| Audit accessibilité | PASS | axe-core WCAG 2.1 A+AA, zéro défaut serious/critical ; rapport `docs/accessibility/` |
| Revue de sécurité | PASS | 8 tests obligatoires S-01 → S-08 prouvés ; aucune vulnérabilité bloquante ; rapport `docs/security/` |
| Build assets Vite | PASS | `npm run build` (vérifié 2026-11-08 ; aucun changement d'assets depuis) |
| Sauvegarde/restauration | PASS | MT-12 : 43 tables, 76 lignes, 75 fichiers identiques, empreintes vérifiées (preuve réelle sur DB de démonstration, T-065) |
| Manuel utilisateur (README) | DISPONIBLE | mis à jour à chaque tâche ; relecture finale T-072 |
| CHANGELOG | DISPONIBLE | `.aidf/docs/CHANGELOG.md` — entrée 1.6.0 complète |

## 4. Gates AIDF

| Phase | Gate d'entrée | Gate de sortie |
|---|---|---|
| intake | PASS | PASS |
| discovery | PASS | PASS |
| specification | PASS | PASS |
| planning | PASS | PASS |
| implementation | PASS | PASS (`implementation-exit`, 2026-11-18) |
| testing | PASS | PASS (`testing-exit`, 2026-11-18 — 176 tests réels) |
| security | PASS | PASS (`security-exit`, 2026-11-18 — approbation humaine Jeremy Ferrier) |
| release | PASS | en cours de validation (build réel + approbation humaine) |

## 4 bis. Vérifications finales de phase release (2026-11-18)

| Vérification | Résultat réel |
|---|---|
| `git status` | arbre propre hors preuves de gate |
| Migrations en attente | aucune (`migrate:status`) |
| `npm run build` | assets produits : `app-CPfeOAEQ.css` (5,63 ko), `app-CiG0bjsp.js` (0,13 ko), 71 ms |
| `php artisan test` | 176 tests / 176 PASS / 697 assertions (31,8 s) |
| `composer audit --no-dev` | 0 avis de vulnérabilité |
| `npm audit --omit=dev` | 0 vulnérabilité |
| Playwright | 10/10 PASS (serveur de démonstration réel) |
| Preuve d'installation vierge | `docs/release/installation-test.md` (4 comptes, PASS) |

Aucun statut `BLOCKED`, `FAIL` ou `NOT_PROVEN` non justifié dans le lot
T-060 → T-072 ; les limites ouvertes sont documentées (§6) et reprises
dans `docs/security/` §6.

## 5. Documentation livrée

- `README.md` — manuel utilisateur complet (installation, rôles, parcours,
  limites) ; vérification finale en T-072.
- `.aidf/docs/CHANGELOG.md` — 1.6.0.
- `docs/accessibility/README.md` — rapport d'accessibilité (T-066).
- `docs/security/README.md` — rapport de revue de sécurité (T-070).
- `docs/planning/` — TASKS, MILESTONES, README-PLAN (gouvernance).
- `.aidf/evidence/` — preuves horodatées par tâche (aucun PASS sans preuve).

## 6. Limites connues de la livraison

Reprises du README §17 et de `docs/security/` §6 — acceptées comme non
bloquantes pour cette release :

- analyse RGPD juridique complète, antivirus sur uploads, chiffrement des
  sauvegardes, politique de conservation, pentest externe : `NOT_PROVEN` ;
- audit manuel d'accessibilité (lecteur d'écran) : complément recommandé ;
- vérifications runtime Qoder (étape manuelle IDE) : WARN / non bloquant ;
- extension PHP GD absente de l'environnement : les tests utilisent des
  uploads simulés (`UploadedFile::fake()->create()`).

## 7. Décision

La version 1.6.0 a été **approuvée pour release le 2026-11-18 par
Jeremy Ferrier** (preuve `release-approval` avec approbation humaine).
Le gate `release-exit` est **PASS** : 7 gates antérieurs PASS, build réel
PASS (1 045 ms), approbation humaine enregistrée. Le cycle AIDF est complet
(8 phases, 16 gates PASS).
