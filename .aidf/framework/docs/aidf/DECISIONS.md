# AIDF Decision Log

Format: decision, status, date, rationale, consequences.
Statuses: PROPOSED, ACCEPTED, REVISIT, SUPERSEDED.

## DEC-001 — Framework identity and scope
- Status: ACCEPTED (2026-10-30)
- AIDF is a governance layer installed inside projects; it does not replace the
  IDE, an existing cockpit, or project architecture.
- Consequence: the framework owns only `.aidf/`, the managed `AGENTS.md` block,
  and explicitly namespaced adapter files (`aidf-*`).

## DEC-002 — Qoder is the first adapter; `generic` is the baseline
- Status: ACCEPTED (2026-10-30)
- Evidence used: official Qoder rules/skills/hooks/agents documentation plus the
  observed `.qoder` layout of installed Qoder 1.106.3.
- Consequence: `src/core/adapters/qoder.mjs` generates `aidf-` prefixed rules,
  skills and the orchestrator agent. Other IDEs can be added without touching
  core modules.

## DEC-003 — Rule activation is reported as NOT_PROVEN, not automatic
- Status: ACCEPTED (2026-10-30)
- Reason: the activation type (Always Apply etc.) is a Qoder UI setting; the
  filesystem cannot prove it.
- Consequence: `qoder.verify()` returns `CONTRACT-PROVEN` for file/contract
  checks and `NOT_PROVEN` for runtime checks (`/skills` discovery, rule
  activation, hook activation). Nothing in AIDF claims runtime activation.

## DEC-004 — Managed AGENTS.md block instead of replacement
- Status: ACCEPTED (2026-10-30)
- Consequence: pre-existing `AGENTS.md` content is preserved byte-for-byte;
  AIDF adds `<`!-- AIDF:BEGIN ... AIDF:END --`>`. Uninstall restores the user
  file and deletes it only if the block was its sole content.

## DEC-005 — Ownership tracking with SHA-256 in `.aidf/manifest.json`
- Status: ACCEPTED (2026-10-30)
- Consequence: files not in the manifest are never overwritten; edited managed
  files produce `CONFLICT` (exit 2); `--force` keeps a timestamped backup.

## DEC-006 — Node ESM runtime with zero dependencies
- Status: ACCEPTED (2026-10-30)
- Reason: the host had `npm.ps1` blocked by execution policy; the framework
  must work with `node` alone.
- Consequence: a deliberately small YAML subset parser is used; registries are
  human-editable YAML, machine state is JSON.

## DEC-007 — YAML subset parser instead of a library
- Status: ACCEPTED (2026-10-31)
- Consequence: supported syntax (nested maps, sequences, inline arrays, quoted
  strings, `|`/`>` and `|-` block scalars) is covered by tests; unsupported
  constructs fail loudly instead of being mis-parsed.

## DEC-008 — Command execution is opt-in and shell-free
- Status: ACCEPTED (2026-10-30)
- Consequence: command checks run only with `--allow-exec` or
  `security.allowCommandExecution=true`; `spawn(…, { shell: false })`; shell
  operators (`|`, `&`, `;`, `<`, `>`, backticks, `$( )`) are rejected before
  execution; output is truncated and recorded as evidence.

## DEC-009 — Spec Kit integration is reference + copy-with-hash
- Status: ACCEPTED (2026-10-30, revised 2026-10-31)
- AIDF documents are durable product truth; Spec Kit specs cover one feature.
- `.specify/memory/constitution.md` may receive a generated AIDF copy marked
  `<!-- AIDF:SOURCE-HASH:… -->`. Any existing Spec Kit constitution without this
  marker is a conflict that AIDF never overwrites (verified behaviour).
- Mapping facts live once; feature specs reference PRD requirement IDs.

## DEC-010 — `specify init` on a non-empty project requires explicit opt-in
- Status: ACCEPTED (2026-10-31)
- Finding (verified): `specify init --here --non-interactive` fails with
  “Current directory is not empty” when AIDF is already installed.
- Consequence: `aidf speckit init` reports `BLOCKED` and requires
  `--specify-force`, which appends `--force` to the specify call. The merge was
  verified to be additive (AGENTS.md, `.aidf/`, `.qoder/` remained intact).

## DEC-011 — Spec Kit skill detection uses both prefixes
- Status: ACCEPTED (2026-10-31)
- Finding (verified): specify 1.2.3 installs `.qoder/skills/speckit-*`
  (hyphen), while earlier docs described `speckit.` (dot).
- Consequence: `specKitStatus` accepts `speckit-` and `speckit.`; verified
  against a real qodercli init (10 skills detected).

## DEC-012 — Resources with unknown licences are BLOCKED and index-only
- Status: ACCEPTED (2026-10-30)
- Consequence: `dictionary-of-ai-coding` is indexed by name/path/commit only;
  `installGitResource` refuses network installs for unknown licences.

## DEC-013 — Documents vs status transitions
- Status: ACCEPTED (2026-10-30)
- `VALIDATED` requires `--by "<name>"` (human accountability); `NOT_APPLICABLE`
  requires a `--note`; `aidf docs status` also updates the document frontmatter
  and manifest hash.

## DEC-014 — Exit code contract
- Status: ACCEPTED (2026-10-30)
- `0` = success, `1` = failure/blocked/refusal, `2` = conflict or contradiction
  (context BLOCKED, install conflicts, Spec Kit constitution conflict).

## REVISIT list
- Qoder project-vs-user skill precedence: docs conflict; AIDF links installs
  into `.qoder/skills` only and never relies on resolution order.
- A `qoder` marketplace/registry adapter (auto-download of AIDF skills) was
  rejected for 0.1.0: no network installs without human authorization.
