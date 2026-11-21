# AIDF — AI Development Framework

**Version 0.3.2** · MIT · Node.js ≥ 20.11 · zéro dépendance runtime

> AIDF est une **couche de gouvernance installable** dans un projet : elle
> encadre le développement assisté par IA (agents, Qoder, Spec Kit) avec des
> preuves mesurables — pas des promesses.

---

## À quoi ça sert (le problème qu'AIDF résout)

Un assistant IA qui code est puissant, mais présente 4 risques réels :

| Risque | Sans AIDF | Avec AIDF |
|---|---|---|
| **Le faux succès** | « Les tests passent » alors que rien n'a tourné | Un test ne compte que s'il a **réellement** été exécuté et enregistré comme evidence hashée |
| **L'écrasement silencieux** | L'agent réécrit vos fichiers | Tout fichier écrit est **dans un manifest** (SHA-256) ; vos fichiers ne sont jamais touchés ; toute écriture sur du managé modifié → `CONFLICT`, votre contenu préservé |
| **Les secrets** | L'agent lit/affiche `.env`, clés… | Politique : **jamais lu, jamais affiché** (noms listés seulement) — vérifié par tests |
| **La dispersion des règles** | Règles éparpillées, jamais appliquées | Des règles/skills **installées dans l'IDE** (Qoder) + un **sélecteur de contexte** qui choisit quoi lire par tâche |

**Utilité en une phrase** : chaque phase (intake → … → release) a des **gates
exécutable** ; rien ne passe sans preuve enregistrée ; les approbations
humaines sont nominatives (`--by "prénom"`).

## Ce qu'AIDF fait / ne fait pas

**Fait** : installer ~190 fichiers gouvernés dans un projet ; 27 commandes CLI
(`--json`, exit 0/1/2) dont les moteurs L1–L3 — artefacts canoniques
(JSON + validation), registre d'exigences, traçabilité exigence→design→
implémentation→test→preuve, Decision Engine déterministe (interprétation
déléguée à l'agent hôte), Design Engine (classifieur + mockups SVG/CSS de
secours), inventaire complet des ressources externes (sha256, licences,
secrets exclus), catalogue et activation explicite des skills tierces,
modèle de menace STRIDE, plan de test par critère d'acceptation, génération
de documentation Markdown avec détection de divergence, pipeline de
delivery (`aidf ops` : build/test/deploy/rollback + monitoring `watch`
borné, exécution conditionnée et evidence), client Figma REST réel
(`validateFigma` prouvé sur un fichier réel), et rapport de
release consolidé ; gérer 8 phases / 16 gates ; chaîner l'evidence
(SHA-256) ; 13 skills built-in + adaptateurs **Qoder** (skills runtime
prouvés par un humain), **OpenCode** (runtime prouvé) et **generic**
(AGENTS.md + `.aidf/skills/`) ; intégrer
GitHub Spec Kit **sans duplication** ; enregistrer des ressources externes
avec gating de licences.

**Ne fait pas** : choisir l'architecture de votre projet ; exécuter des
commandes sans permission (`--allow-exec` ou config) ; toucher au réseau sans
autorisation ; lire vos secrets ; se substituer à l'IDE ; publier/pusher.

## Démarrage rapide (3 commandes), résultat réel

```powershell
# via npm (package framework-aidf) ou depuis un clone/portable
npx framework-aidf install --target C:\projets\mon-projet --adapter qoder --with-hooks
# → [INFO] created=~190 updated=0 unchanged=0 conflicts=0 failed=0

cd C:\projets\mon-projet
node .aidf\bin\aidf.mjs doctor          # → tout PASS sauf runtime Qoder (NOT_PROVEN, voir QODER.md)
node .aidf\bin\aidf.mjs context --task "ma première tâche"
node .aidf\bin\aidf.mjs release report  # → bilan de release READY/NOT_READY
```

Le **premier workflow complet documenté pas-à-pas** (projet TaskFlow, du
« dicté humain » à la release) est dans `UTILISATION.md` §Exemple réel.

## Les commandes (27 verbes)

| Commande | Rôle |
|---|---|
| `install` / `init` | Installer la gouvernance dans un projet (identique, idempotent) |
| `update` | Rafraîchir les fichiers managés (backups auto ; `--force` en cas d'édition locale) |
| `uninstall` | Retirer AIDF (`--yes` requis ; `--keep-data` garde docs+evidence) |
| `doctor` | Bilan de santé complet (contrats, manifest, evidence, sécurité) |
| `scan` | Inventaire du projet (`--record` → report + evidence ; secrets en noms seulement) |
| `context --task` | Choisir les documents/skills à lire pour une tâche ; `BLOCKED` si contradiction |
| `workflow status/enter/exit/next` | Avancer **par gates**, jamais en sautant |
| `validate --gate / --phase` | Évaluer une gate (avec `--allow-exec` pour les commandes) |
| `evidence add/capture/verify` | Enregistrer/vérifier les preuves (chaîne SHA-256, `--human --by`) |
| `docs list/show/status/generate` | Les 11 documents projet + statuts ; `generate` rend les artefacts en Markdown (`--check` détecte les éditions manuelles) |
| `skills list/validate/show/link/refresh/sources/catalog/enable` | Catalogue builtin + skills tierces (licences, doublons, provenance `AIDF-SOURCE.json`) |
| `config get/set` | Configuration (`commands.*`, `security.*`) |
| `resources inventory/coverage/reconcile/inspect` | Inventaire sha256 des ressources, politique de licences, dérive lock/manifest |
| `speckit status/init/sync` | Intégration GitHub Spec Kit (mapping, constitution) |
| `artefacts list/show/validate/verify` | Artefacts canoniques (exigences, designs, décisions, tests, threat model) |
| `requirements add/list/show/update/delete/coverage` | Registre d'exigences `REQ-F/NF-###` + couverture |
| `traceability report/verify/missing/broken` | Chaîne de traçabilité et liens cassés/manquants |
| `decision evaluate` | Decision Engine déterministe → artefact `decisions/` DRAFT (approbation humaine requise) |
| `design classify/run` | Classifieur de complexité + workflows de design (mockups SVG/CSS de secours) |
| `security analyze/show` | Modèle de menace STRIDE `TM-###` généré depuis la détection projet |
| `tests plan/coverage` | Plan de test `TP-###` (1 cas par critère) + couverture exigence→test |
| `release report` | Rapport de release READY/NOT_READY (`--record`, `--strict`, `--allow-exec`) |
| `ops init/plan/run/rollback/watch/status` | Pipeline de delivery `.aidf/workflows/pipeline.yaml` (build/test/deploy, exécution conditionnée à `--allow-exec`, rollback enregistré, monitoring `watch` borné, rapports + evidence) |
| `help`, `version` | Aide et version |

Contrat de codes de sortie : **0** succès · **1** échec/refus · **2** conflit/contradiction.

## Vocabulaire de statuts (honnêteté intégrée)

- **Documents** : `DRAFT`, `VALIDATED` (exige `--by`), `BLOCKED`, `OUTDATED`, `NOT_APPLICABLE` (exige `--note`).
- **Gates** : `PASS`, `BLOCKED`, `FAIL`, `NOT_PROVEN`, `NOT_APPLICABLE` — un `NOT_PROVEN` est un état fiable, pas une défaillance.
- **Skills** : `DISCOVERED` → `VALIDATED` → `REGISTERED` → `AVAILABLE` (`BLOCKED`, `INCOMPATIBLE`).
- **Rapports** : `PROVEN` (mesuré) · `CONTRACT-PROVEN` (fichiers/contrats) · `NOT_PROVEN` (demande une action humaine) · `BLOCKED` · `NOT_APPLICABLE`.

## Ce qui est installé dans votre projet

```
AGENTS.md                    bloc managé (votre contenu préservé)
.qoder/                   règles + 13 skills + agent + hooks (adaptateur qoder)
.opencode/skills/         13 skills (adaptateur opencode — runtime prouvé)
.aidf/skills/             13 skills (adaptateur generic — tout hôte AGENTS.md)
.aidf/AIDF.md              point d'entrée agent
.aidf/bin/aidf.mjs         lanceur portable
.aidf/framework/           copie autonome du framework
.aidf/docs/                11 documents avec statuts + docs/generated/
.aidf/artefacts/           exigences, décisions, test-plan, threat-model
.aidf/registry/            skills, activation-map, dependencies, resources.lock.json
.aidf/workflows/           phases.yaml + gates.yaml + pipeline.yaml (modifiables projet)
.aidf/evidence/            preuves chaînées + index
.aidf/state/, reports/, backups/, logs/
```

## Documentation

**Guides pratique (français)** : `INSTALLATION.md` · `UTILISATION.md` · `QODER.md`

**Références techniques (anglais)**, dans `docs/aidf/` : `ARCHITECTURE.md`,
`DECISIONS.md` (DEC-001…014), `SKILLS.md`, `SPECKIT.md`, `SECURITY.md`,
`VALIDATION.md` (les mesures), `LIMITATIONS.md`, `COMPATIBILITY.md`,
`INVENTORY.md`, `FRAMEWORK.md`.

**Journal** : `CHANGELOG.md` (v0.3.1 : `ops watch` monitoring borné + client Figma REST réel prouvé `fully_verified` ; v0.3.0 : pipeline de delivery `aidf ops` ; v0.2.0 : moteurs artefacts/requirements/traceability/décision/design, inventaire ressources, skills tierces, adaptateur OpenCode, threat model, test plan, docs generate, release report, migration 0.1.0→0.2.0 prouvée). Package publié : **`framework-aidf`** sur npm.

## Preuves de qualité (mesurées, pas déclarées)

- **217/217 tests** `node --test` (incluant un E2E du CLI : install → doctor →
  scan → skills → context → gate → config → speckit → uninstall).
- **E2E en situation réelle** : projet TaskFlow gouverné de bout en bout,
  2 bugs réels détectés/corrigés par les gates, conflit d'édition humain
  traité en exit 2 + backup, Spec Kit installé en merge, 23 evidence
  chaînées vérifiées.
- Validation détaillée : `docs/aidf/VALIDATION.md`.

## Licence

MIT (`LICENSE`). Le corpus de cours privé `MODULES/` de l'auteur **n'est pas
distribué** (registry index-only, exclusions strictes : `STAGE/**`, `.env*`,
`*.pem`, `*.crt`, `*.key`, secrets et arborescences de TP).
