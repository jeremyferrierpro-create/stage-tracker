# AIDF Installation and Update Guide

Status: VALIDATED

## 1. Prerequisites

- Node.js >= 20.11 (validated on 24.21.0). No npm packages are required.
- Optional: Qoder IDE (for the `qoder` adapter), `specify` CLI (for Spec Kit).
- Windows: PowerShell execution policy blocked `npm.ps1`; AIDF needs `node` on
  PATH, nothing else.

## 2. Install AIDF into a project

From inside a project (recommended):

```powershell
node C:\path\to\aidf\bin\aidf.mjs install --adapter qoder --with-hooks
```

From outside, targeting the project:

```powershell
node C:\path\to\aidf\bin\aidf.mjs install --target C:\path\to\project --adapter qoder --with-hooks
```

`aidf init <dir>` is an alias of `install` and accepts the same options.

Options:

| Option | Meaning |
|---|---|
| `--adapter qoder\|generic` | IDE adapter (default: `qoder`). |
| `--with-hooks` | Install `.aidf/hooks/*.mjs` and declare SessionStart/PostToolUse hooks in `.qoder/settings.json`. |
| `--dry-run` | Print the action plan only; nothing is written (not even logs). |
| `--force` | Only re-applies AIDF-managed files that changed locally (backup kept). |
| `--name <name>` | Override the project name in config. |

Verified behaviour (2026-10-31, real runs on Windows):

- Existing `AGENTS.md` content is preserved, the AIDF block is appended.
- Existing `.qoder/rules/**` files are untouched; only `aidf-*.md` is created.
- Existing `.qoder/settings.json` keys survive hook merging.
- `.env`, `.git`, `node_modules` are skipped; no secret content is read.
- Idempotency: re-running install reports `created=0 updated=0 unchanged=N`.

## 3. Post-install checklist

```powershell
node .aidf/bin/aidf.mjs doctor
node .aidf/bin/aidf.mjs scan --record
node .aidf/bin/aidf.mjs skills validate
node .aidf/bin/aidf.mjs setup resources
```

Then in Qoder:

1. Restart/open the project; run `/skills` to confirm the 13 `aidf-*` skills.
2. Settings → Rules: set `aidf-00-core` and `aidf-10-security` to
   **Always Apply** (this manual step is what turns the NOT_PROVEN runtime
   checks into proven ones).
3. With hooks: restart Qoder once so the SessionStart hook loads.

## 4. Update

Re-run the newer distribution against the project:

```powershell
node C:\path\to\new-aidf\bin\aidf.mjs update --target C:\path\to\project --adapter qoder
```

Only manifest-owned files are refreshed (backups kept). Project documents,
evidence and workflow state are preserved. Running `update` from the installed
copy re-applies the *current* version; to change versions use a newer
distribution checkout.

## 5. Uninstall

```powershell
node .aidf/bin/aidf.mjs uninstall --dry-run   # preview
node .aidf/bin/aidf.mjs uninstall --yes       # remove everything AIDF owns
node .aidf/bin/aidf.mjs uninstall --yes --keep-data   # keep .aidf/docs + evidence
node .aidf/bin/aidf.mjs uninstall --yes --force       # also remove edited managed files (backups kept)
```

Verified: modified managed files are preserved with `CONFLICT` (exit 2) and
removed only via `--force` with a backup; `AGENTS.md` is restored (deleted only
when the block was its only content); hook entries are removed from
`.qoder/settings.json` without touching other settings; `.env` and project
files are never touched.

## 6. CI / non-interactive usage

All commands accept `--json` and are deterministic. Example:

```powershell
node .aidf/bin/aidf.mjs validate --phase testing --kind exit --allow-exec --json
```

Exit codes: `0` success, `1` failure/blocked, `2` conflict/contradiction.
