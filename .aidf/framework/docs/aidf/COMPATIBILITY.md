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
| qoder | PROVEN runtime partiel (2026-11-21, vérifié par un humain, evidence `runtime-verification`) : `/aidf` liste ~14 skills `aidf-*`, `/skills` montre `aidf-project-context` ; install + verifier restent CONTRACT-PROVEN ; règles "Always Apply" et hooks runtime non encore vérifiés | `.qoder/rules`, `.qoder/skills`, `.qoder/agents`, `.qoder/settings.json`, `AGENTS.md` | validated against Qoder 1.106.3 layout + official docs |
| opencode | PROVEN runtime (2026-11-21): real `opencode run` session read the AIDF block and discovered project skills; requires a git worktree for project-skill discovery | `AGENTS.md`, `.opencode/skills/` | verified on OpenCode 1.18.34; non-git projects get files but no skill discovery (PARTIAL) |
| generic | PROVEN (AGENTS.md managed block + built-in skills under `.aidf/skills/`) | `AGENTS.md`, `.aidf/skills/` | any AGENTS.md-compatible host; skills are files on disk — runtime loading depends on the agent reading the AIDF registry |

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
| `dictionary-of-ai-coding` licence | RESOLVED 2026-11-21 | MIT declared by the owner (no LICENSE file in the clone); registry updated, skills indexable |
| `pencil` redistribution | BLOCKED | GPL-2.0 licence; excluded from the MIT AIDF distribution by human decision |
| `.qoder/commands` (legacy) | NOT_APPLICABLE | dropped by Qoder IDE 1.24+; AIDF uses skills |
| npm global install of AIDF | declared, not exercised in 0.1.0 | package ships `bin` but the recommended flow is the portable checkout |
| Qoder marketplace plugin packaging | NOT_APPLICABLE | out of scope for 0.1.0 |

## 6. Version compatibility

- Config schema: `schemaVersion: 1` (rejects others with a clear message).
- Manifest schema: `schemaVersion: 1`.
- AIDF registries are forward-compatible by additive entries; unknown skill
  statuses keep the catalog importable but report `INCOMPATIBLE`.

## 7. 0.2.0 L0 compatibility notes

- **Packaging**: the npm tarball now includes `INSTALLATION.md`,
  `UTILISATION.md`, `QODER.md`, `CHANGELOG.md` and `demo/`. Existing installs
  are unaffected until they reinstall/update.
- **Gate semantics (deliberate defect fixes)**:
  - a blocking check can no longer resolve to `NOT_APPLICABLE` (it is
    `BLOCKED`);
  - a gate whose blocking checks are all skipped is `NOT_PROVEN`;
  - a gate with no blocking check must declare `applicability: informative` to
    PASS;
  - `doc-status` reads the document frontmatter and fails on a mismatch with
    `config.json`.
- **Evidence**: `evidence verify` is stricter and checks the whole chain. The
  JSON output keeps `checked` and `bad` and adds `ok` and `problems`.
- **Approvals**: `security.requireAuthenticatedApproval` is optional. When
  absent (old configs) behaviour is compatible: declared approvals still pass
  gates, but `doctor` reports `WARN` and evidence records
  `approval.authenticated: false`.
- **Frozen documents**: install/update still never rewrite project documents,
  registries, workflows or knowledge, even with `--force` (regression-tested).
- **CLI**: 26 verbs, exit codes 0/1/2, `--json` shape additive only.
- **New commands (0.2.0)**: `artefacts`, `requirements`, `traceability`,
  `decision`, `design`, `security`, `tests`, `release`, plus
  `resources inventory|coverage|reconcile` and `skills sources|catalog|enable`.
  All are additive; no 0.1.0 verb changed semantics.
- **Decision Engine**: the optional LLM analyzer (`AI_MODEL`/`callLLM`) was
  removed. AIDF owns no model; interpretation is delegated to the invoking
  agent (`analysis.delegation`). The deterministic path is unchanged.
- **Migration**: covered by `tests/migration.test.mjs` (legacy 0.1.0 fixture
  upgrades additively, files preserved, engines usable) and verified manually
  against a real 0.1.0 install copy (148 → 198 managed files, 0 conflicts).
