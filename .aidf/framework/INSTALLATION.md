# AIDF — INSTALLATION (mode d'emploi pas-à-pas)

Ce guide décrit l'installation **réelle et vérifiée** d'AIDF dans un projet,
sa mise à jour et sa désinstallation. Prérequis, options, sorties attendues et
dépannage inclus.

---

## 1. Prérequis

| Prérequis | Détail |
|---|---|
| Node.js ≥ 20.11 | Validé sur v24.21.0 (Windows 11). `node --version` doit répondre. |
| aucun paquet npm | AIDF n'a **aucune dépendance runtime** (vérifié : fonctionne sans `npm install`). |
| Qoder IDE (optionnel) | Pour l'adaptateur `qoder` : règles/skills/agent/hooks dans `.qoder/`. Sans Qoder, utilisez `--adapter generic` (bloc `AGENTS.md` seulement). |
| `specify` CLI (optionnel) | Pour l'intégration Spec Kit (`aidf speckit …`) — sinon les gates spec-kit passent en `NOT_APPLICABLE`. |
| git (optionnel) | Pour des diff d'evidence riches ; sans git, AIDF fonctionne (evidence par liste de fichiers). |

## 2. Installer AIDF dans un projet

### 2.1 Depuis l'intérieur du projet (recommandé)

```powershell
cd C:\chemin\vers\votre-projet
node C:\chemin\vers\aidf\bin\aidf.mjs install --adapter qoder --with-hooks
```

### 2.2 Depuis l'extérieur (cible explicite)

```powershell
node C:\chemin\vers\aidf\bin\aidf.mjs install --target C:\projets\mon-projet --adapter qoder --with-hooks
```

### 2.3 Sortie attendue (réelle)

```
[INFO] AIDF install — mon-projet (qoder)
[INFO] created=135 updated=0 unchanged=0 conflicts=0 failed=0 would=0
[INFO] Next: node .aidf/bin/aidf.mjs doctor
```

### 2.4 Options

| Option | Effet |
|---|---|
| `--adapter qoder` | Génère règles + 13 skills + agent + (avec hooks) hooks dans `.qoder/` (défaut). |
| `--adapter generic` | Uniquement le bloc managé `AGENTS.md` (tout IDE compatible AGENTS.md). |
| `--with-hooks` | Installe aussi `.aidf/hooks/*.mjs` + entrées `SessionStart`/`PostToolUse` dans `.qoder/settings.json` (vos clés/rootSettings conservées). |
| `--dry-run` | **N'écrit absolument rien** (pas même les logs) ; affiche le plan `WOULD_*`. |
| `--force` | Ne concerne que les fichiers AIDF déjà présents et modifiés localement (backup automatique dans `.aidf/backups/`). |
| `--name <nom>` | Nom du projet dans la config. |

### 2.5 Ce qui est préservé (invariants, testés)

- Un `AGENTS.md` existant : votre contenu est conservé, le bloc AIDF est
  ajouté entre marqueurs `AIDF:BEGIN/END`.
- Vos propres règles `.qoder/rules/**` : jamais touchées (seules `aidf-*` sont créées).
- Un `.qoder/settings.json` existant : fusion **additive** (vos hooks restent).
- `.env`, `.git`, `node_modules` : ignorés ; **le contenu des secrets n'est jamais lu**.
- Ré-exécuter `install` : `created=0 updated=0 unchanged=N` (idempotent).

## 3. Après l'installation : vérifier en 4 commandes

```powershell
node .aidf\bin\aidf.mjs doctor          # bilan de santé (attendu : rien de FAIL)
node .aidf\bin\aidf.mjs scan --record   # inventaire + evidence + report SCAN.md
node .aidf\bin\aidf.mjs skills validate # 13 skills AVAILABLE attendues
node .aidf\bin\aidf.mjs workflow status # phase courante (intake)
```

Doctor `--json` donne la même chose en machine-lisible. Les 3 checks
`runtime-*` de l'adaptateur restent `NOT_PROVEN` tant que l'étape Qoder
ci-dessous n'a pas été faite par un humain — c'est voulu et documenté.

> **IMPORTANT — étape Qoder (3 minutes, une fois par projet)** : suivre les
> étapes 1→5 de `QODER.md` (ouvrir le projet, `/skills`, Always Apply sur
> `aidf-00-core` et `aidf-10-security`), puis enregistrer la preuve :
> `aidf evidence add --type qoder-runtime-activation --human --by "votre_nom"`.

## 4. Mettre à jour

Depuis une copie **plus récente** du framework :

```powershell
node C:\chemin\vers\nouveau-aidf\bin\aidf.mjs update --target C:\projets\mon-projet --adapter qoder --with-hooks
```

- Seuls les fichiers **managés** sont rafraîchis ; vos documents
  (`.aidf/docs/*`), evidence et état workflow sont préservés.
- Un fichier managé que vous avez édité → `CONFLICT` (exit 2), contenu
  préservé. Résolution : fusionner à la main, ou `update --force` (backup
  automatique créé d'abord).
- Idempotence vérifiée : `created=0 updated=14 unchanged=121 conflicts=0`.

## 5. Désinstaller

```powershell
node .aidf\bin\aidf.mjs uninstall --dry-run        # aperçu (rien n'écrit)
node .aidf\bin\aidf.mjs uninstall --yes            # retire tout ce qu'AIDF possède
node .aidf\bin\aidf.mjs uninstall --yes --keep-data # garde .aidf/docs + evidence
node .aidf\bin\aidf.mjs uninstall --yes --force    # inclut aussi les fichiers managés édités (backup)
```

Vérifié en réel : sans `--yes` → **refus (exit 1)** ; `AGENTS.md` restauré
(bloc retiré ; fichier supprimé uniquement s'il ne contenait que le bloc) ;
vos `existing.md` dans `.qoder/rules/` restent ; les entrées de hooks AIDF
sont retirées de `.qoder/settings.json` sans toucher au reste ; `.env` intact.

## 6. CI / usage non-interactif

Toute commande accepte `--json` et suit le contrat 0/1/2. Exemple :

```powershell
node .aidf\bin\aidf.mjs doctor --json
node .aidf\bin\aidf.mjs validate --phase testing --kind exit --allow-exec --json
```

Pour exécuter des commandes déclarées (tests, build, audit) depuis les gates,
autorisez une fois dans la config : `aidf config set
security.allowCommandExecution true` — ou utilisez `--allow-exec` ponctuel.
C'est shell-free, opérateurs refusés, sortie tronquée et enregistrée.

## 7. Un install par projet

AIDF se résout **un root par projet** (marqueur `.aidf/manifest.json`).
Workspaces multi-projets : installez AIDF dans chaque projet ; le workspace
lui-même peut être gouverné comme n'importe quel projet (c'est le cas de ce
dépôt).

## 8. Dépannage installation

| Symptôme | Cause | Remède |
|---|---|---|
| `AIDF is not initialized …` | Lancement hors projet installé | Lancez dans le projet ou ajoutez `--target <chemin>`. |
| `… conflicts=1`, exit 2 | Fichier managé édité localement | Fusion manuelle, ou `install/update --force` (backup auto). |
| Doctor `runtime-* NOT_PROVEN` | Étape UI Qoder non faite | Suivre `QODER.md` puis enregistrer l'evidence humaine. |
| Doctor `spec-kit NOT_APPLICABLE` | Pas de `.specify/` | Optionnel : `aidf speckit init` (+ `--specify-force` si projet non vide). |
| Les gates « command » disent BLOCKED | Exécution non autorisée | `--allow-exec` une fois, ou `config set security.allowCommandExecution true`. |
| `specify init` échoue « not empty » | Projet déjà peuplé (normal) | `aidf speckit init --specify-force` (opt-in explicite, merge vérifié non-destructif). |
| `skills validate` signale `INCOMPATIBLE` | Frontmatter non conforme (nom >64 / description >1024) | Corriger la SKILL.md source, puis `skills refresh`. |
