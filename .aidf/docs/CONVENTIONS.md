---
doc: CONVENTIONS
status: VALIDATED
version: 1.0.0
source: stage-tracker-specification
updated: 2026-11-04
validated_by: Jeremy Ferrier
---

# CONVENTIONS — Règles de travail du projet

## 1. Langue

- Code, noms de variables, tables et commits : **anglais**.
- Interface utilisateur, messages, documentation de gouvernance : **français**.
- Aucune chaîne utilisateur codée en dur hors fichiers de traduction
  (`lang/fr/*.php`) lorsque l'i18n est en place.

## 2. Style PHP

- PSR-12, formatage par **Laravel Pint** (`vendor/bin/pint`).
- `declare(strict_types=1);` dans les classes de domaine.
- Typage explicite des paramètres et retours ; pas de `mixed` sans justification.
- Enums PHP pour les statuts ; Value Objects pour les types métier.
- Pas de logique métier dans les contrôleurs ni dans les modèles Eloquent lourds.

## 3. Nommage

| Élément | Convention | Exemple |
|---|---|---|
| Tables | snake_case pluriel | `daily_logs` |
| Modèles | StudlyCase singulier | `DailyLog` |
| Clés étrangères | `<singulier>_id` | `internship_id` |
| Contraintes | `fk_`, `uq_`, `ck_`, `ix_` | `uq_commits_repository_sha` |
| Colonnes booléennes | `is_`, `has_`, `can_` | `is_published` |
| Routes | kebab-case | `/w/{workspace}/daily-logs` |
| Composants Livewire | StudlyCase | `DailyLogForm` |
| Tests | `it_<comportement>` | `it_rejects_cross_workspace_access` |

## 4. Git

- Branche principale : `main` (protégée en production).
- Branches : `feat/<id>-<slug>`, `fix/<id>-<slug>`, `docs/<slug>`, `chore/<slug>`.
- Commits : **Conventional Commits** (`feat:`, `fix:`, `docs:`, `test:`,
  `refactor:`, `chore:`, `security:`), message en anglais, impératif.
- Un commit = un changement cohérent ; pas de secrets, pas de fichiers générés.
- Aucun `git push --force` sur `main`.
- Chaque commit significatif référence l'unité de travail (`refs: T-xxx`).

## 5. Tests

- Framework : PHPUnit (Feature/Unit) ; Playwright pour les parcours navigateur.
- Nommage : `it_<comportement_attendu>` ou `test_<comportement>`.
- Chaque règle métier a au moins un test ; chaque invariant a un test dédié.
- Les tests multi-tenant MT-01..MT-12 sont bloquants.
- Aucun test marqué « skipped » sans justification et ticket associé.
- Les résultats de test sont enregistrés comme preuves AIDF.

## 6. Documentation

- Documents AIDF dans `.aidf/docs/` ; annexes techniques dans `docs/`.
- Toute décision structurante est consignée dans `DECISIONS.md` (format ADR).
- Toute évolution visible est consignée dans `CHANGELOG.md`.
- Les diagrammes utilisent Mermaid (versionnable, lisible en diff).
- Aucune capture d'écran ne remplace un document texte pour une décision.

## 7. Preuves AIDF

- Toute affirmation significative est accompagnée d'une preuve
  (`aidf evidence add --type <type> --note "..."`).
- Les validations humaines sont nominatives (`--human --by "..."`).
- Les statuts de preuve sont exclusivement PASS / BLOCKED / FAIL / NOT_PROVEN.
- Aucune preuve n'est modifiée après enregistrement (append-only).

## 8. Données

- Aucune donnée réelle dans les seeders ou tests.
- Les données COAB restent dans leur workspace dédié.
- Les identifiants exposés utilisent des UUID lorsque la devinabilité est un risque.
- Les dates sont stockées en UTC, affichées en heure locale.

## 9. Revue de code

- Toute modification passe par une revue (humaine ou agent + preuve).
- Checklist : tests exécutés, autorisations, isolation, validation, audit,
  absence de secret, documentation.
- Un désaccord bloquant est consigné et arbitré par le propriétaire.

## 10. README — manuel utilisateur (DEC-ST-013)

- Le `README.md` est le **mode d'emploi de l'outil**, destiné à un camarade de
  formation qui clone le dépôt pour son propre stage.
- À la fin de chaque tâche, les sections concernées sont mises à jour dans le
  même commit (mapping : `docs/planning/README-PLAN.md`).
- Une section ne documente jamais une fonctionnalité non implémentée ou non
  testée ; elle porte alors la mention `À VENIR — livré par T-xxx`.
- Les commandes affichées doivent avoir été réellement exécutées.
- Une tâche sans impact utilisateur peut ne pas modifier le README, avec
  justification en revue.
