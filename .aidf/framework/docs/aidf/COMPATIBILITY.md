# AIDF Compatibility Matrix

Status: VALIDATED for the cells marked; others are declared, not exercised.

## 1. Supported hosts

| Component | Requirement | Validated on |
|---|---|---|
| Node.js | >= 20.11 (ESM, `import.meta.dirname`) | 24.21.0 (Windows) |
| OS | Windows 10/11, Linux, macOS (Node paths) | Windows 11 26200 x64 |
| Shell integration | none required | PowerShell 5.1 (`npm.ps1` blocked → use `cmd /c` for npm) |
| Git | optional (evidence capture and `specify` init degrade gracefully) | git 2.56 |

## 2. IDE adapters

| Adapter | Status | Project scope | Notes |
|---|---|---|---|
| qoder | CONTRACT-PROVEN (install + verifier); runtime activation requires manual UI steps | `.qoder/rules`, `.qoder/skills`, `.qoder/agents`, `.qoder/settings.json`, `AGENTS.md` | validated against Qoder 1.106.3 layout + official docs |
| generic | PROVEN (AGENTS.md managed block only) | `AGENTS.md` | any AGENTS.md-compatible host |

## 3. GitHub Spec Kit

| Combination | Result |
|---|---|
| `specify` CLI 1.2.3 + `--integration qodercli` | validated: init (empty dir), merge (`--force`), `.qoder/skills/speckit-*` |
| spec-kit source 1.2.6.dev0 (commit `ba7894d`) | referenced only (templates/docs); not assumed to match CLI |
| older CLI versions | declared compatible down to 1.0.0 (mapping schema), not exercised |

## 4. Coexistence rules

| Existing artifact | AIDF behaviour | Verified |
|---|---|---|
| `AGENTS.md` exists | append managed block, user content preserved, uninstall restores | yes |
| `.qoder/rules/*` (non-AIDF) | untouched | yes |
| `.qoder/settings.json` with hooks | AIDF entries merged as a new group; existing entries kept; uninstall removes only AIDF groups | yes |
| `.specify/` already present | no reinit; sync honours existing constitution (CONFLICT) | yes |
| `.env` or other secret files | name listed only, content never read, never copied into reports | yes |
| non-git project | evidence capture falls back to the file list provided by the caller | yes |
| duplicate skill names across roots | both flagged, statuses BLOCKED (never silently resolved) | yes |

## 5. Known incompatible / blocked

| Item | Status | Reason |
|---|---|---|
| `dictionary-of-ai-coding` redistribution | BLOCKED | no LICENSE file in clone; index-only |
| `.qoder/commands` (legacy) | NOT_APPLICABLE | dropped by Qoder IDE 1.24+; AIDF uses skills |
| npm global install of AIDF | declared, not exercised in 0.1.0 | package ships `bin` but the recommended flow is the portable checkout |
| Qoder marketplace plugin packaging | NOT_APPLICABLE | out of scope for 0.1.0 |

## 6. Version compatibility

- Config schema: `schemaVersion: 1` (rejects others with a clear message).
- Manifest schema: `schemaVersion: 1`.
- AIDF registries are forward-compatible by additive entries; unknown skill
  statuses keep the catalog importable but report `INCOMPATIBLE`.
