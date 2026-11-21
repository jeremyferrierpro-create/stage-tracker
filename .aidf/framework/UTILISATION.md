# AIDF — UTILISATION (mode d'emploi du quotidien)

Ce guide explique **comment travailler** sous AIDF au jour le jour : la
session, le contexte, les gates, les preuves, les documents, les skills, la
config et Spec Kit — avec un exemple complet réel en fin de document.

---

## 1. Les 3 règles du quotidien

1. **Le contexte d'abord** : avant toute évolution, la tâche est passée au
   sélecteur (`aidf context --task "…"`). Il liste précisément quels
   documents et quelles skills lire — jamais tout d'un coup.
2. **Les gates ensuite** : on avance par phases (`intake → … → release`).
   Chaque transition exécute une gate ; un `NOT_PROVEN`/`BLOCKED` est
   l'outil qui vous protège, il se résout avec une preuve, pas un contournement.
3. **L'evidence partout** : toute affirmation importante a un enregistrement
   hashé (`aidf evidence …`). Un PASS sans evidence n'existe pas.

## 2. La session type (humain ou agent)

```powershell
node .aidf\bin\aidf.mjs doctor                 # santé (exit 0 attendu)
node .aidf\bin\aidf.mjs workflow status        # où suis-je ?
node .aidf\bin\aidf.mjs context --task "ajouter le paiement PayPal au backoffice"
# → liste des docs/skills à lire ; BLOCKED (exit 2) = contradiction à régler AVANT
```

Le contexte écrit `.aidf/state/context/latest.md` : c'est le **contrat de
lecture** entre l'humain et l'agent. Corollaire honnête : exécuter `context`
avec `--no-write` ne force aucune transition (une gate qui exige le fichier
écrit le refuse — comportement observé en réel).

## 3. Les documents du projet (`.aidf/docs/`)

11 documents : `constitution`, `PRD`, `TRD`, `APPFLOW`, `UI-UX`, `BACKEND`,
`PLAN`, `SECURITY`, `CONVENTIONS`, `DECISIONS`, `CHANGELOG`.

```powershell
aidf docs list
aidf docs show SECURITY
aidf docs status PRD VALIDATED --by "Jeremy"               # VALIDATED = nominatif
aidf docs status UI-UX NOT_APPLICABLE --note "CLI seulement"
```

- Les templates posés par AIDF sont **des drafts honnêtes** (`[TO FILL]`) :
  ils doivent être remplis à partir d'une analyse réelle, jamais inventés.
- Les documents « contradiction-sensitive » (`SECURITY`, `TRD`, `BACKEND`,
  `PRD`) en `BLOCKED`/`OUTDATED` mettent **tout le contexte en `BLOCKED`** :
  c'est voulu.

## 4. Le workflow : 8 phases, 16 gates

```
intake → discovery → specification → planning → implementation → testing → security → release
```

Chaque phase a une gate `entry` et `exit`. Progresser :

```powershell
aidf workflow enter <phase>            # exécute <phase>-entry
aidf workflow exit  <phase>            # exécute <phase>-exit → passe à la suivante
aidf validate --gate planning-exit     # évaluer une gate seule
```

Une gate affiche chaque check avec son statut et sa raison. Les types de
checks : `file-exists`, `doc-status`, `evidence`, `command`, `previous-gate`,
`manual`. Les gates `release-exit` vérifient **la chaîne des gates
antérieures** (`prior-gates PASS`) : impossible d'arriver au release en
sautant des étapes.

### Commandes des gates (tests réels)

```powershell
aidf config set commands.test  'node --test "tests/*.test.mjs"'
aidf config set security.allowCommandExecution true   # ou --allow-exec ponctuel
aidf workflow exit testing --allow-exec
# → [test-command] Command exited 0 in 573 ms  (exécution RÉELLE, shell-free)
```

## 5. Evidence (les preuves)

```powershell
aidf evidence add --type spec-validation  --human --by "Jeremy" --note "PRD approuvé"
aidf evidence add --type implementation-change --by "agent" --note "module d'auth"
aidf evidence capture --type testing        --note "run des tests"
aidf evidence verify                        # re-h contractualise toute la chaîne
```

- Les types comptent : les gates cherchent des types précis
  (`task-classification`, `spec-validation`, `plan-approval`, `test-run`,
  `security-review`, `release-approval`, `implementation-change`…).
- `--human --by "<nom>"` : une approbation humaine nominative ; sans elle, la
  gate porteuse reste bloquée (vérifié : même un agent honnête ne peut pas
  « décider » à la place).
- Chaque enregistrement est repris dans une chaîne `previous → hash`
  (SHA-256) ; toute altération est détectable par `evidence verify` et
  `doctor`.

## 6. Skills

13 skills internes (`aidf-*`) couvrent : contexte, produit, exigences,
architecture, BDD, UX, front, back, tests, sécurité, DevOps, docs,
transition. La sélection est faite par `context` (toujours activées : socle ;
sinon par phase et mots-clés, plafonnées).

```powershell
aidf skills list
aidf skills validate        # cycles, compatibilité, registre
aidf skills show aidf-testing-validation
aidf skills link <id>      # lier une skill externe (junction) dans .qoder/skills

# Skills tierces (depuis 0.2.0)
aidf skills sources         # inventaire des ressources externes indexées
aidf skills catalog         # catalogue avec statut dérivé de la licence
aidf skills enable owasp-security   # copie un skill canonique + provenance AIDF-SOURCE.json
```

Une skill tierce n'est copiée que si sa licence le permet (MIT/Apache/BSD…)
; GPL et licences inconnues → `BLOCKED`/index-only. Secrets et exécutables
jamais copiés (`--allow-scripts` explicite pour les rares exceptions).

## 7. Config utile

| Clé | Usage |
|---|---|
| `commands.test` / `commands.build` / `commands.securityAudit` | Commandes réelles que les gates exécuteront (absente → check `NOT_APPLICABLE`, non bloquant). |
| `security.allowCommandExecution` | `true` pour autoriser sans `--allow-exec`. |
| `security.allowedCommands` | Liste blanche de clés de commandes. |
| `security.allowNetwork` | Toute récupération réseau (clonage de ressource) reste opt-in. |
| `context.maxSkills` | Plafond de skills chargées par contexte (défaut 5). |

## 8. Spec Kit (features)

Spec Kit travaille au niveau **feature** ; AIDF au niveau **produit**. Ils
s'interlockent sans duplication :

```powershell
aidf speckit status          # .specify présent ? skills speckit-* ? constitution ?
aidf speckit init --allow-exec --specify-force   # si le projet n'est pas vide
aidf speckit sync --adopt    # publie la constitution AIDF (copie avec hash) ; CONFLICT si invalide
```

- Les specs de feature (`specs/<feature>/spec.md`) **référencent les IDs de
  requirements du PRD** (`FR-3`), elles ne les reformulent pas.
- Le mapping est exporté dans `.specify/aidf/mapping.md`.
- Si Spec Kit possède sa propre constitution : **CONFLICT (exit 2), jamais
  écrasée** — décision humaine (conserver/template ou adopter).

## 9. Ressources externes

```powershell
aidf resources inspect <id>       # inventaire sha256 d'une ressource + politique de licence
aidf resources inventory          # manifeste complet du corpus (hash par fichier)
aidf resources coverage           # couverture : ressources manquantes, skills en doublon
aidf resources reconcile          # dérive lock/manifest (fichiers changés/manquants)
aidf setup resources              # génère .aidf/registry/resources.lock.json
```

Licence inconnue → `BLOCKED`, index-only (jamais copié/redistribué). La
resistance au dishonesty : le verrou inclut le commit, l'arbre hashé, la
décision de licence. Les fichiers secrets (`.env`, `*.pem`, `*.key`…) sont
détectés **par leur chemin, jamais ouverts** ; les médias/binaires volumineux
sont exclus par `sources.yaml`.

## 10. Les moteurs d'ingénierie (depuis 0.2.0)

Tous produisent des **artefacts canoniques** sous `.aidf/artefacts/`
(JSON validé par schéma) — jamais de succès déclaré sans exécution réelle.

```powershell
# Artefacts
aidf artefacts list|show|validate|verify

# Exigences : registre REQ-F/REQ-NF + couverture
aidf requirements add --type FUNCTIONAL --title "Export PDF" --ac "contient les totaux"
aidf requirements list|coverage

# Traçabilité : exigence → design → implémentation → test → preuve
aidf traceability report|verify|missing|broken

# Décision technologique : moteur déterministe (contraintes dures, scores,
# éliminations, incertitudes) → artefact DRAFT ; l'interprétation est
# déléguée à l'agent qui lit le document
aidf decision evaluate --options options.json --constraints constraints.json

# Design : classifieur de complexité + workflows (mockups SVG/CSS de secours,
# client Figma REST réel si figma.fileId + FIGMA_TOKEN fournis)
aidf design classify --context ctx.json
aidf design run --context ctx.json --write

# Sécurité : modèle STRIDE déterministe depuis la détection projet → TM-### DRAFT
aidf security analyze --write
aidf security show

# Tests : plan TP-### (1 cas par critère d'acceptation) + couverture honnête
aidf tests plan --write
aidf tests coverage

# Docs : rend les artefacts canoniques en Markdown (docs/generated/) avec
# provenance sha256 ; --check détecte les éditions manuelles
aidf docs generate [--check]

# Release : agrège gates + docs + evidence + git → READY/NOT_READY honnête
aidf release report [--record] [--strict] [--allow-exec]
```

### Pipeline de delivery (`aidf ops`, depuis 0.3.0)

```powershell
aidf ops init                     # écrit .aidf/workflows/pipeline.yaml (projet, jamais écrasé)
aidf ops plan                     # valide et liste les étapes — dry run, rien n'exécute
aidf ops run --allow-exec         # build → test → deploy séquentiel, fail-fast
aidf ops rollback --allow-exec    # rejoue les commandes de rollback du dernier deploy
aidf ops watch --cycles 10 --interval 30 --allow-exec   # monitoring borné (health checks)
aidf ops status                   # dernier run enregistré
```

- `pipeline.yaml` déclare `steps` (kind `build`/`test`/`deploy`/`verify`/`custom`)
  et `rollback` + `monitoring.steps`. C'est un fichier **projet** : relisez-le
  avant d'autoriser l'exécution.
- Exécution = `runCommand` : **sans shell**, opérateurs (`|`, `&&`, `>`, …)
  rejetés, timeout par étape. Permission exigée : `--allow-exec` ou
  `security.allowCommandExecution=true` — sinon chaque étape = `NOT_PROVEN`.
- Chaque run écrit `.aidf/reports/OPS-*.{json,md}` ; `--record` ajoute une
  evidence `ops-run`/`ops-rollback`/`ops-watch`. Un `PASS` signifie « les
  commandes ont rendu exit 0 », pas « le déploiement est sain ».

## 11. Dépannage rapide

| Symptôme | Signification | Remède |
|---|---|---|
| Gate `NOT_PROVEN` | Une preuve précise manque | Lire la ligne de la gate : le type d'evidence/des événements attendus est écrit dans la raison. |
| Gate `BLOCKED` | Une condition de sécurité/groupe non remplie | Souvent : approbation `--human --by`, ou document en `BLOCKED`. |
| Contexte `BLOCKED` (exit 2) | Doc sensible en contradiction | Régler le document (`docs status VALIDATED --by …` ou `--note`). |
| `CONFLICT` sur un managé (exit 2) | Vous avez édité un fichier AIDF | Fusion manuelle ou `update --force` (backup auto). |
| gate `command` BLOCKED | Exécution non autorisée / commande non configurée | `commands.*` + `--allow-exec`/config. |
| `speckit sync` exit 2 | Constitution externe (hors AIDF) | Décision humaine : conserver ou déplacer puis `sync --adopt`. |

---

## 12. Exemple réel complet (test E2E TaskFlow, documenté)

Ce parcours a été exécuté pour de vrai le 2026-10-31 (`docs/aidf/VALIDATION.md`) :

| Étape | Commande clé | Résultat réel |
|---|---|---|
| Demandisation humaine | — (dictée) | « app de tâches : priorités, fait, persistance, tests » |
| Gouvernance | `install … --adapter qoder --with-hooks` | created=135, conflicts=0 |
| Contexte | `context --task …` (précédée `--no-write` en 1er essai) | liste ciblée (PRD/SECURITY/TRD/BACKEND + 2 skills) |
| Gate intake | `validate --gate intake-entry` puis `workflow exit intake` | PASS puis exit 1 par manque de contexte écrit + evidence `task-classification` → corrigé → **exit 0** |
| Docs | `docs status * VALIDATED --by Jeremy` | 10 VALIDATED + UI-UX NOT_APPLICABLE |
| Spec/plan | `workflow exit specification/planning` | blocs initiaux (`human approval` requis) → evidences `--human` → **exit 0** |
| Implémentation | code + `evidence add --type implementation-change` | CLI TaskFlow, tailles honnêtes, + un **vrai bug NPE détecté par les tests** et corrigé |
| Testing | `commands.test` + `workflow exit testing --allow-exec` | commande réelle « exited 0 in 573 ms » ; 2e run requis avec evidence `test-run` → **exit 0** |
| Security | revue + `evidence add --type security-review --human` | `security-audit NOT_APPLICABLE`, approbation PASS |
| Release | `workflow exit release` | `prior-gates PASS` (7 gates), `build NOT_APPLICABLE`, approval → **cycle complete** |
| Spec Kit | `speckit init --specify-force --allow-exec`, `sync --adopt` | 10 skills + mapping ; **CONFLICT préservé** (constitution externe conservée par décision TF-0006) |
| Geste humain | édition manuelle d'une règle + `update` | **exit 2 CONFLICT** ; résolu par `update --force` avec **backup créé** |
| Acceptation | `node bin\taskflow.mjs add/list/done/rm` | tri conforme, persistance sur disque, exit 0 |

**Ce que cet exemple prouve** : le cadre a refusé deux fois de laisser passer
l'agent sans preuve, refusé d'écraser des fichiers, détecté un vrai bug via
les tests exigés, et l'humain a une app qui tourne — avec **23 evidence
vérifiées** et un manifest intègre.
