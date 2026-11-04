# AIDF — AI Development Framework

**Version 0.1.0** · MIT · Node.js ≥ 20.11 · zéro dépendance runtime

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

**Fait** : installer 131–135 fichiers gouvernés dans un projet ; exposer ~16
commandes CLI (`--json`, exit 0/1/2) ; gérer 8 phases / 16 gates ; chaîner
l'evidence (SHA-256) ; 13 skills + adaptateur Qoder (règles, agent
orchestrateur, hooks) ; intégrer GitHub Spec Kit **sans duplication**
(constitution générée avec hash de source, conflits jamais écrasés) ;
enregistrer des ressources externes avec gating de licences.

**Ne fait pas** : choisir l'architecture de votre projet ; exécuter des
commandes sans permission (`--allow-exec` ou config) ; toucher au réseau sans
autorisation ; lire vos secrets ; se substituer à l'IDE ; publier/pusher.

## Démarrage rapide (3 commandes), résultat réel

```powershell
# depuis un clone/portable du framework, cible = votre projet
node C:\chemin\vers\aidf\bin\aidf.mjs install --target C:\projets\mon-projet --adapter qoder --with-hooks
# → [INFO] created=135 updated=0 unchanged=0 conflicts=0 failed=0

cd C:\projets\mon-projet
node .aidf\bin\aidf.mjs doctor          # → tout PASS sauf runtime Qoder (NOT_PROVEN, voir QODER.md)
node .aidf\bin\aidf.mjs context --task "ma première tâche"
```

Le **premier workflow complet documenté pas-à-pas** (projet TaskFlow, du
« dicté humain » à la release) est dans `UTILISATION.md` §Exemple réel.

## Les 16 commandes

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
| `docs list/show/status` | Les 11 documents projet + statuts nominatifs |
| `skills list/validate/show/link/refresh` | Catalogue + validation + liaison skill externe |
| `config get/set` | Configuration (`commands.*`, `security.*`) |
| `resources list/inspect`, `setup resources` | Registry externe + verrou reproductible |
| `speckit status/init/sync` | Intégration GitHub Spec Kit (mapping, constitution) |
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
.qoder/rules/aidf-*.md       3 règles (socle, sécurité, contexte)
.qoder/skills/aidf-*/        13 skills (compatibles Qoder en nom/description)
.qoder/agents/               orchestrateur aidf
.qoder/settings.json         hooks fusionnés (vos clés conservées)
.aidf/AIDF.md                point d'entrée agent
.aidf/bin/aidf.mjs           lanceur portable
.aidf/framework/             copie autonome du framework
.aidf/docs/                  11 documents avec statuts
.aidf/registry/              skills, activation-map, dependencies, resources.lock.json
.aidf/workflows/             phases.yaml + gates.yaml (modifiables projet)
.aidf/evidence/              preuves chaînées + index
.aidf/state/, reports/, backups/, logs/
```

## Documentation

**Guides pratique (français)** : `INSTALLATION.md` · `UTILISATION.md` · `QODER.md`

**Références techniques (anglais)**, dans `docs/aidf/` : `ARCHITECTURE.md`,
`DECISIONS.md` (DEC-001…014), `SKILLS.md`, `SPECKIT.md`, `SECURITY.md`,
`VALIDATION.md` (les mesures), `LIMITATIONS.md`, `COMPATIBILITY.md`,
`INVENTORY.md`, `FRAMEWORK.md`.

**Journal** : `CHANGELOG.md` (v0.1.0 : liste complète des ajouts/correctifs).

## Preuves de qualité (mesurées, pas déclarées)

- **43/43 tests** `node --test` (incluant un E2E du CLI : install → doctor →
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
