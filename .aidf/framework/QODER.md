# AIDF — QODER (guide d'intégration de l'IDE)

Qoder est le premier adaptateur supporté (`--adapter qoder`). Ce guide
explique ce qui est installé, **ce qu'un humain doit faire une fois dans
l'IDE**, et les limites honnêtes.

---

## 1. Ce qu'AIDF installe pour Qoder (vérifié au fichier près)

| Emplacement | Contenu |
|---|---|
| `AGENTS.md` | Bloc managé `AIDF:BEGIN/END` pointant vers `.aidf/AIDF.md` (contenu utilisateur préservé, restauration à l'uninstall) |
| `.qoder/rules/aidf-00-core.md` | Règle socle : inspecter avant modifier, statuts, evidence |
| `.qoder/rules/aidf-10-security.md` | Règle sécurité : secrets jamais lus, exécution opt-in, fichiers protégés |
| `.qoder/rules/aidf-20-context.md` | Règle contexte : lire `.aidf/state/context/latest.md` avant d'agir |
| `.qoder/skills/aidf-*/SKILL.md` | 13 skills premières (nom ≤ 64, description ≤ 1024 : contraintes Qoder respectées et testées) |
| `.qoder/agents/aidf-orchestrator.md` | Agent orchestrateur (outils Read/Grep/Glob/Bash + liste des skills) |
| `.qoder/settings.json` (option `--with-hooks`) | Entrées `SessionStart` (contexte de session) et `PostToolUse` (audit) **fusionnées** — VOS settings existants sont conservés |
| `.aidf/hooks/session-context.mjs`, `audit.mjs` | Hooks : rappel de phase / docs bloquées ; audit **métadonnées seulement** (jamais le contenu des fichiers) |

Coexistence : les `speckit-*` générés par Spec Kit et vos propres `*.md` de
règles coexistent sans collision (les fichiers AIDF portent tous le préfixe `aidf-`).

## 2. Activation runtime — 3 étapes HUMAINES (5 minutes, une fois par projet)

Ce que le système de fichiers ne peut PAS prouver : que Qoder **charge** les
règles, découverte les skills et exécute les hooks. D'où le statut
`NOT_PROVEN` assumé dans `doctor`. Voici la procédure exacte :

### Étape 1 — Découverte des skills
1. Ouvrir le projet dans Qoder et attendre la fin de l'indexation.
2. Ouvrir la palette/chat et exécuter la commande **`/skills`**.
3. Verifier la présence des 13 `aidf-*` (et, si installé, des 10 `speckit-*`).

### Étape 2 — Activation des règles
1. Ouvrir **Settings → Rules** dans Qoder.
2. Mettre `aidf-00-core` et `aidf-10-security` en **Always Apply**.
3. Laisser `aidf-20-context` en `Model Decision` (ou `Specific Files`).
4. Le type d'activation est un **réglage UI** de Qoder : il n'est pas encodé
   dans le fichier `.md` (c'est pourquoi le filesystem ne peut pas le prouver).

### Étape 3 — Test des hooks (si `--with-hooks`)
1. Redémarrer Qoder (chargement du `SessionStart`).
2. Faire une modification minuscule dans un fichier du projet.
3. Vérifier qu'une ligne d'audit apparaît dans `.aidf/logs/audit-<date>.jsonl`.

### Enregistrer la preuve (transforme NOT_PROVEN en PROVEN nominatif)

```powershell
aidf evidence add --type qoder-runtime-activation --human --by "votre_nom" --note "/skills: 13 aidf-*; Always Apply: core + security; audit JSONL reçu"
```

Après cette evidence, `doctor` reflète l'activation prouvée par un humain
identifié. Sans cette evidence, aucune commande AIDF ne la simulera — par
conception.

## 3. Ce que les règles font concrètement pour vous

Quand l'IA codeuse travaille dans Qoder :

1. Elle **lit le contexte** (`.aidf/state/context/latest.md`) au démarrage de
   la session (le hook SessionStart y contribue).
2. Elle **ne touche pas** : vos fichiers hors manifest, vos secrets, le bloc
   `AIDF:BEGIN/END` de `AGENTS.md`.
3. Elle **enregistre** : toute affirmation de test/release passe par les
   gates et l'evidence nominative.
4. Elle **sait quoi lire** : les mots-clés de la tâche déclenchent le bon
   ensemble docs/skills (ex. « rgpd/cnil » → SECURITY + security-review).

## 4. Limites documentées (honnêtes)

| Limite | Détail | Position AIDF |
|---|---|---|
| Type d'activation des règles | Réglage UI, non encodé dans le fichier | Rappel écrit dans la règle ; preuve humaine exigée |
| Precedence skills projet vs utilisateur | La doc Qoder se contredit (IDE vs CLI) | AIDF installe au niveau **projet** uniquement et ne dépend pas de cet ordre |
| `.qoder/commands` | Legacy (abandonné par Qoder 1.24+) | Non généré ; les skills sont le mécanisme actuel (vérifié avec Spec Kit qodercli) |
| Hooks sous environnement Qoder | S'exécutent dans le process de l'IDE | `audit.mjs` ne logue que chemins relatifs + nom d'outil (jamais de contenu) → secret-safe par construction |
| Runtime Qoder non installé (`qodercli` absent du PATH) | Doctor le signale | L'activation reste une étape humaine dans l'IDE, non automatisable honnêtement |

## 5. Dépannage Qoder

| Symptôme | Cause probable | Remède |
|---|---|---|
| `/skills` ne montre pas les `aidf-*` | Projet non réindexé / skills hors projet | Réouvrir le projet ; vérifier `.qoder/skills/aidf-*/SKILL.md` présents ; relancer `aidf doctor`. |
| Les règles « ne s'appliquent pas » | Toujours `Model Decision` | les passer en **Always Apply** (étape 2) puis enregistrer l'evidence. |
| Pas de fichier `audit-*.jsonl` | Hooks non chargés / install sans `--with-hooks` | Réinstaller avec `--with-hooks`, redémarrer Qoder. |
| Conflit de hook dans `settings.json` | Édition manuelle hasardeuse | Le merge AIDF est additif ; comparer avec `.aidf/backups/<ts>/` si besoin. |
