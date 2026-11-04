# AIDF Inventory

- Date: 2026-10-31 (Europe/Paris).
- Scope: host machine of the AIDF author, populated with real clones and tooling.
- Method: direct inspection of file systems, installed versions (`<tool> --version`
  where available) and official documentation retained as references. No content
  or value of a `.env` file was read.

## 1. Host environment

| Item | Value | Evidence |
|---|---|---|
| OS | Windows 11 x64, build 26200 | `os.release()` / system information at inventory time |
| Node.js | v24.21.0 | `node --version` |
| npm | 11.19.0 (execution policy blocks `npm.ps1`; use `cmd /c npm` or plain `node`) | Phase 0 check |
| Python | 3.14 | `python --version` (Phase 0) |
| uv | installed (`specify` CLI depends on it) | `uv --version` (Phase 0) |
| git | 2.56 | `git --version` (Phase 0) |
| Qoder IDE | 1.106.3 installed (`~/.qoder` present) | Phase 0 file-system check |
| specify CLI | `specify 1.2.3` (`C:\Python314\Scripts\specify.exe`) | executed during validation |
| GitHub CLI | `gh` present on PATH | `where gh` (Phase 0) |

The `vibecoding` workspace itself is not a git repository.

## 2. Workspace resources (as referenced by `assets/registry/sources.yaml`)

| Resource | Path | Licence | Status | Evidence |
|---|---|---|---|---|
| github-spec-kit | `../resources/spec-kit` (source 1.2.6.dev0, commit `ba7894d`) | MIT | PRESENT | clone + `aidf setup resources` inspection |
| mattpocock-skills | `../resources/skills` (v1.3.2, commit `a496f55`) | MIT | PRESENT | clone inspection |
| superpowers | `../resources/superpowers` (v7.0.1, commit `2ce8aa1`) | MIT | PRESENT | clone inspection |
| agent-rules-books | `../resources/agent-rules-books` (commit `a7d7649`) | MIT | PRESENT | clone inspection |
| dictionary-of-ai-coding | `../resources/dictionary-of-ai-coding` (commit `ed1ebe`) | unknown | BLOCKED (index-only) | no LICENSE file in clone |
| jeremy-web-modules | `../resources/MODULES` (corpus privé, ~321 docs de cours) | proprietary-owner | PARTIAL (index-only) | registre + lock (2026-10-31) |
| owasp-asvs | `../resources/owasp-asvs` (5.0.0, commit `9ccce0e`) | CC-BY-SA-4.0 | PRESENT | clone épinglé (autorisation humaine) |
| qoder-docs | https://docs.qoder.com | proprietary-docs | reference | verified pages retained in DECISIONS |
| owasp-asvs | https://owasp.org/...ASVS | CC-BY-SA-4.0 | NOT_INSTALLED (v unknown) | referenced by name only |
| typescript-handbook | https://www.typescriptlang.org/docs/handbook/ | Apache-2.0 | reference | cited, not copied |

## 3. Qoder capabilities verified (documentation + real CLI/IDE checks)

- Project rules live in `.qoder/rules/*.md`; the activation type (Always Apply /
  Model Decision / Specific Files / Apply Manually) is a UI setting — it is not
  encoded in the file, so AIDF cannot prove it from the filesystem.
- Skills: project-level `.qoder/skills/<name>/SKILL.md`, user-level
  `~/.qoder/skills`; `name` ≤ 64 lowercase-hyphen chars, `description` ≤ 1024.
- Qoder IDE 1.24+ dropped `.qoder/commands`; Spec Kit's `qodercli` integration
  installs skills under `.qoder/skills/` (verified with specify 1.2.3).
- Hooks (`SessionStart`, `PostToolUse`, …) in `.qoder/settings.json`,
  `.qoder/settings.local.json` or `~/.qoder/settings.json`.
- Custom agents: `.qoder/agents/*.md` with YAML frontmatter.
- `AGENTS.md` is honoured automatically by Qoder; project rules take precedence.

## 4. Document and status vocabulary

- Documents: `PRD`, `TRD`, `APPFLOW`, `UI-UX`, `BACKEND`, `PLAN`, `SECURITY`,
  `CONVENTIONS`, `constitution`, `DECISIONS`, `CHANGELOG`.
- Document statuses: `DRAFT`, `VALIDATED`, `BLOCKED`, `OUTDATED`, `NOT_APPLICABLE`.
- Gate statuses: `PASS`, `BLOCKED`, `FAIL`, `NOT_PROVEN`, `NOT_APPLICABLE`.
- Skill statuses: `DISCOVERED`, `VALIDATED`, `REGISTERED`, `AVAILABLE`,
  `BLOCKED`, `INCOMPATIBLE`.
- Final-report statuses: `PROVEN`, `CONTRACT-PROVEN`, `BLOCKED`, `NOT_PROVEN`,
  `NOT_APPLICABLE`.

## 5. Open deltas recorded at inventory time

1. Qoder documentation does not state how project vs user skills are resolved
   (IDE doc and CLI doc disagree); AIDF does not rely on this precedence.
2. The `dictionary-of-ai-coding` clone has no LICENSE file: content stays
   index-only until the owner clarifies the licence.
3. `npm.ps1` is blocked by the execution policy; AIDF has zero npm runtime
   dependencies, so this has no runtime impact.
