# Matrice d'écarts AIDF 0.1.0 → 0.2.0

- Date: 2026-11-21
- Branche: `feat/aidf-0.2.0`
- Baseline mesurée: `npm test` → **200/200 PASS** (après correction de 3 régressions WIP)
- État au 2026-11-21 (release 0.2.0): `npm test` → **212/212 PASS**, version `0.2.0`
  (`AIDF_VERSION` + `package.json`), packaging npm revérifié, migration 0.1.0→0.2.0
  prouvée (`tests/migration.test.mjs` + copie réelle de l'install 0.1.0 : 148→198
  fichiers gérés, 0 conflit).
- Méthode: inspection du code réel + exécution réelle des commandes sur `../resources/`
  et sur un projet temporaire (`install --adapter opencode` + `resources inventory --write`
  + `skills sources` + `skills enable owasp-security`).

Statuts utilisés: PROVEN (exécuté et vérifié), CONTRACT-PROVEN (contrat fichiers vérifié,
runtime non prouvé), PARTIAL, NOT_PROVEN, BLOCKED, ABSENT.

## 1. Socle 0.1.0 (LOT 0)

| Domaine | État | Preuve |
|---|---|---|
| 26 verbes CLI (+ `ops` → 27 en Unreleased) | PROVEN | `src/cli.mjs` COMMANDS; e2e `tests/cli.test.mjs` + `tests/cli.engines.test.mjs` + `tests/cli.security.test.mjs` + `tests/cli.delivery.test.mjs` + `tests/ops.test.mjs` |
| Phases/gates/checks | PROVEN | `assets/workflows/`, `src/core/workflow.mjs`, gates tests |
| Evidence Engine + `verifyEvidenceChain` | PROVEN | `src/core/evidence.mjs`, `evidence verify` |
| Packaging npm | PROVEN | `tests/packaging.test.mjs` (pack → install → init → doctor) |
| `run-demo.mjs` | PROVEN (non rejoué ce jour) | `demo/run-demo.mjs` présent; `npm run demo` non exécuté |
| Idempotence install/uninstall | PROVEN | e2e cli.test.mjs + installer tests |

Régressions WIP corrigées le 2026-11-21:
- `tests/adapters.test.mjs` attendait 2 adaptateurs → mis à jour pour `opencode`.
- `tests/packaging.test.mjs`: `tar` GNU sur Windows interprétait `C:\…` comme hôte
  distant → `--force-local` + chemins relatifs.
- `src/core/resources.mjs` `inspectResource` crashait sur fichier > 2 Gio
  (`sha256File` = `readFile` entier) → hash limité à 64 Mio, comptage `skipped`
  (secrets + oversized), champs `hashedFiles`/`skipped` ajoutés.
- `assets/registry/sources.yaml` MODULES: exclusions médias/binaires ajoutées
  (ISO 3,9 Gio, mp4) — cohérent avec la note « documents de cours uniquement ».

## 2. Resource Engine (LOT 3)

| Capacité | État | Preuve |
|---|---|---|
| Inventaire fichier par fichier (sha256) | PROVEN | `resources inventory --write` réel: 18 ressources, 12 533 fichiers, 914 Mio |
| Secrets jamais lus | PROVEN | 23 chemins skippés (`isSecretPath`), jamais ouverts |
| Politique de licences | PROVEN | `LICENSE_POLICIES` + tests `resources.v2.test.mjs` |
| Couverture déclarée vs inventoriée | PROVEN | `resources coverage` réel: 18/18, complete=true |
| Détection doublons de skills | PROVEN | 7 doublons (6 identiques intra ui-ux-pro-max, 1 `code-review` non identique spec-kit vs mattpocock) |
| Réconciliation lock/manifest | PARTIAL | `resources reconcile` existe; lock `resources.lock.json` requis — non généré dans le projet de test |
| `installGitResource` (vendor épinglé) | NOT_PROVEN | requiert `--allow-network`; non exécuté (pas d'autorisation réseau) |

## 3. Skills Engine (LOT 3)

| Capacité | État | Preuve |
|---|---|---|
| Catalogue skills sources | PROVEN | `skills sources` réel: 81 fichiers SKILL.md, 74 uniques AVAILABLE, 7 DUPLICATE |
| Statuts licence→disponibilité | PROVEN | INDEX_ONLY/BLOCKED/INCOMPATIBLE/DUPLICATE dans `source-skills.mjs` + tests |
| Activation (`skills enable`) | PROVEN | copie réelle `owasp-security` → `.opencode/skills/` (5 fichiers + `AIDF-SOURCE.json`) |
| Scripts exécutables refusés par défaut | PROVEN | `copySourceSkill` skip `.sh/.ps1/…` sauf `--allow-scripts` |
| Sélection contextuelle des skills sources | PARTIAL | `selectSourceSkills` = matching par triggers, pas de moteur de dépendances complet |
| Traçabilité d'usage (preuve d'activation) | PARTIAL | `AIDF-SOURCE.json` enregistre la copie; pas de journal d'usage agent |
| Skills builtin 0.1.0 (13) | PROVEN | registry + adapter install + tests |

## 4. Adaptateurs IDE (LOT 4)

| Adaptateur | État | Limite |
|---|---|---|
| qoder | **PROVEN partiel (runtime skills, 2026-11-21, humain)** | `/aidf` liste ~14 skills `aidf-*`, `/skills` montre `aidf-project-context` (vérifié par le propriétaire, evidence `runtime-verification` enregistrée dans `.aidf/evidence/`) ; règles "Always Apply" et hooks runtime toujours NOT_PROVEN |
| generic (AGENTS.md) | PROVEN | bloc managé idempotent + skills built-in installés sous `.aidf/skills/` (découverts via registry ; chargement runtime = responsabilité de l'agent hôte) |
| opencode | **PROVEN (runtime, 2026-11-21)** | session réelle `opencode run` : l'agent a cité les bonnes commandes AIDF et listé les skills `aidf-*`/`owasp-security` ; `opencode debug skill` découvre les 14 skills **uniquement si le projet est un worktree git** (vérifié : sans `.git`, skills ignorés → check `git-worktree` ajouté, PARTIAL le cas échéant) |
| autres IDE | ABSENT | aucun adaptateur supplémentaire |

## 5. Decision Engine (LOT 5)

| Capacité | État | Preuve |
|---|---|---|
| Moteur déterministe (contraintes dures/mous, ranking) | PROVEN | `src/core/technology-decision/*` + tests |
| Génération document de décision (JSON/MD) | PROVEN | `generator.mjs` + tests |
| Interprétation LLM | HORS PÉRIMÈTRE 0.2.0 | Par design : AIDF est un framework de gouvernance, pas une application — il ne possède ni ne configure de modèle. Le moteur émet les faits déterministes (contraintes, scores, éliminations, incertitudes) ; l'interprétation des compromis/risques/architecture est **déléguée à l'agent IA qui invoque la commande** (`analysis.delegation.delegatedTo = "invoking-agent"`). L'ancien `analyzer-llm.mjs` (`AI_MODEL`/`callLLM`) a été retiré : chemin mort sans valeur pour un framework. |
| Exposition CLI (`aidf decision evaluate`) | PROVEN | commande + artefact `decisions/*.json` + export MD |
| ADR persistés dans `.aidf/artefacts/decisions/` | PROVEN | enveloppe artefact validée, statut DRAFT, approbation humaine requise |

## 6. Design Engine (LOT 6)

| Capacité | État | Preuve |
|---|---|---|
| Classification de complexité | PROVEN | `complexity-classifier.mjs` + tests |
| Workflows par complexité | PROVEN | `engine.mjs` + tests |
| Wireframes SVG / maquettes HTML (fallback) | PROVEN | `fallback/*` + tests |
| Client Figma réel (REST api.figma.com) | **PROVEN (2026-11-21)** | `figma/validators.mjs` appelle la vraie API : token vérifié (`/v1/me` → identité réelle), fichier `2fio4wPbYEFEbqY2k800E0` « Mes Orchidées » accessible → `validateFigma` = `fully_verified` (evidence `figma-verification` dans `.aidf/evidence/`) ; token via `figma.apiKey`/`figma.token` ou `FIGMA_TOKEN` ; parité visuelle reste NOT_PROVEN |
| Parité visuelle (capture) | NOT_PROVEN | `visual-parity/capture.mjs` présent; navigateur non vérifié |
| Exposition CLI (`aidf design classify|run`) | PROVEN | outputs fichiers réels (SVG wireframes, CSS mockup, FALLBACK.md) |

## 7. Security by Design & Test Engineering (LOT 7)

| Capacité | État | Preuve |
|---|---|---|
| Compétence `aidf-security-review` + skill `owasp-security` (source) | AVAILABLE | registry + catalogue |
| Gates security workflow | PROVEN | `assets/workflows/`, validate tests |
| Modèle de menace STRIDE (`security/threat-model.json`) | PROVEN | `aidf security analyze|show` — génération déterministe depuis `detectProject` + config, artefact `TM-###` DRAFT persisté sous `.aidf/artefacts/security/` ; revue humaine requise avant VALIDATED |
| Cycle TDD outillé (RED→GREEN→refactor) | PARTIAL | `aidf tests plan` génère un artefact `TP-###` (1 cas planifié par critère d'acceptation, types inférés) et `aidf tests coverage` mesure la couverture exigence→test ; l'exécution RED→GREEN reste la responsabilité du projet (pas de moteur de scaffold de tests) |
| Tests E2E framework | ABSENT | dépend du projet cible |

## 8. Artefacts & traçabilité (L1)

| Capacité | État | Preuve |
|---|---|---|
| Enveloppe + validation schémas artefacts | PROVEN | `src/core/artefacts/*` + `artefacts.validation.test.mjs` |
| Registre exigences | PROVEN | `src/core/requirements/registry.mjs` + tests |
| Chaîne de traçabilité (liens) | PROVEN | `src/core/traceability/chain.mjs` + tests ; `findMissingLinks` corrigé (résolution réelle des cibles + préfixes d'ID) |
| Contrat de liens L1-C complet | PARTIAL | test `traceability.link.test.mjs` retiré au commit 27a9581; commandes `traceability` exposées mais la sémantique complète des liens reste à consolider |
| Commandes CLI artefacts/requirements/traceability | PROVEN | `aidf artefacts|requirements|traceability` implémentées + `tests/cli.engines.test.mjs` (5 tests e2e) |
| Commandes CLI decision/design | PROVEN | `aidf decision evaluate` (artefact DRAFT + approbation humaine requise) et `aidf design classify|run` (outputs SVG/CSS/MD réels) |
| `docs generate/regenerate/reconcile` (L1-D) | PARTIAL | `aidf docs generate [--check]` rend les artefacts canoniques en Markdown sous `.aidf/docs/generated/` avec en-tête de provenance (source + sha256) et détection de divergence (édition manuelle signalée, régénération écrasante) ; la dérivation JSON←Markdown d'ADR-004 (documents canoniques existants) reste non implémentée |
| Couverture déterministe exigence→test→preuve | PARTIAL | `aidf tests coverage` relie exigences ↔ cas planifiés (TP) ↔ artefacts `TEST-*` ; `requirements coverage` (Evidence Engine) expose toujours la couverture par preuves PASS |

## 9. Delivery & Operations (LOT 8)

| Capacité | État |
|---|---|
| Skill `aidf-devops-engineering` | AVAILABLE |
| Phase `release` + gates | PROVEN (workflow) |
| Rapport de release (`aidf release report`) | PROVEN | évalue tous les gates en lecture seule (pas d'enregistrement), agrège statuts docs/CHANGELOG/evidence/artefacts, écrit `.aidf/reports/RELEASE-<date>.{json,md}`, verdict READY/NOT_READY, `--strict` pour CI, `--record` pour evidence `release-report` |
| Pipeline build/test/deploy/rollback (`aidf ops`, Unreleased) | PROVEN | `src/core/ops.mjs` + `src/commands/ops.mjs` : `.aidf/workflows/pipeline.yaml` projet, exécution séquentielle via `runCommand` (sans shell), fail-fast, `--allow-exec`/`security.allowCommandExecution` exigé, rapports `OPS-*` + evidence `ops-run`/`ops-rollback`, rollback du dernier deploy réussi. Testé e2e (`tests/ops.test.mjs`) |
| Monitoring continu (`aidf ops watch`, Unreleased) | PROVEN | `pipeline.monitoring.steps` exécutés en cycles bornés (`--cycles`/`--duration`/`--interval`), même permission `--allow-exec`, arrêt sur premier échec, rapports `OPS-WATCH-*` + evidence `ops-watch`. Tests e2e `tests/ops.test.mjs` |

## 10. Gouvernance obligatoire

| Capacité | État | Limite honnête |
|---|---|---|
| Session-start protocol (AIDF.md) | PROVEN | instructions lues par l'agent |
| Hooks Qoder (`session-context.mjs`) | CONTRACT-PROVEN | exécution runtime IDE non prouvée |
| Contexte assemblé par tâche (`aidf context`) | PROVEN | testé e2e |
| Blocage gate sans preuve | PROVEN | fail-closed testé |
| Détection de contournement | PARTIAL | `scan` + contradictions BLOCKED; pas de mécanisme anti-contournement actif |
| Portée réelle | — | AIDF impose des instructions/fichiers; un agent externe peut les ignorer — à documenter |

## 11. Ressources `../resources/` (LOT 2)

| Élément | Statut |
|---|---|
| 18 ressources déclarées dans `sources.yaml` | 100 % inventoriées (coverage.complete=true) |
| 12 533 fichiers hashés, 81 SKILL.md (74 uniques valides) | PROVEN |
| `dictionary-of-ai-coding` | BLOCKED — licence inconnue (index-only) |
| `pencil` | BLOCKED — GPL-2.0, décision humaine 2026-11-21 |
| `jeremy-web-modules` | PARTIAL — corpus propriétaire, index-only, exclusions appliquées |
| `owasp-asvs` | PRESENT — CC-BY-SA-4.0, référence |

## 12. Décisions humaines requises / blocages

| # | Décision | Statut |
|---|---|---|
| D1 | Licence `dictionary-of-ai-coding` | RÉSOLU 2026-11-21 — MIT déclarée par le propriétaire (fichier LICENSE absent du clone, déclaration enregistrée dans sources.yaml) |
| D2 | Commit du WIP + corrections baseline | En attente d'autorisation |
| D3 | Vérification runtime IDE | OpenCode PROVEN (session réelle) ; Qoder PROVEN partiel (skills runtime vérifiés par humain ; règles/hooks restent NOT_PROVEN) |
| D4 | `AI_MODEL` pour le Decision Engine LLM | RÉSOLU 2026-11-21 — hors périmètre par décision : interprétation déléguée à l'agent hôte, `analyzer-llm.mjs` supprimé |
| D5 | Commandes CLI manquantes | FAIT pour artefacts/requirements/traceability/decision/design/security/tests/release ; `docs generate` implémenté en PARTIAL (vues Markdown des artefacts, pas la dérivation ADR-004) |
| D6 | Publication npm / push git | Non autorisé |
