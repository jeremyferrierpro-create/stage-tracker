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

## DEC-015 — Document frontmatter is canonical; config is a projection
- Status: ACCEPTED (2026-11-20, L0)
- Context: `aidf docs status` updated both `config.json` and the Markdown
  frontmatter, but gates read only `config.json`. A stale or hand-edited config
  could therefore claim `VALIDATED` while the file was still a `DRAFT` template.
- Problem: the framework reported a gate PASS without a real document.
- Options:
  1. Keep config as canonical (rejected: hidden state, easy to fake).
  2. Read the file on every check (chosen).
  3. Generate both from a database (out of scope for 0.2.0 L0).
- Decision: `doc-status` reads `.aidf/docs/<DOC>.md`, requires a frontmatter
  `status`, fails on a config/file mismatch, and applies `allowed`/`disallowed`
  to the file value. `doctor` keeps the same cross-check.
- Consequences: a mismatch is `FAIL`; a missing file is `BLOCKED`; no silent
  PASS is possible. Existing projects that only changed `config.json` will now
  see a real failure until the file is aligned.
- Risks: legacy documents without frontmatter block gates. Mitigation: the
  templates already ship frontmatter; the error names the file.
- Compatibility: no CLI verb, exit code or check type changed.
- Tests: `tests/workflow.test.mjs` (matching, mismatch, missing file),
  `tests/doctor.test.mjs` (config/file mismatch).

## DEC-016 — Exhaustive applicability rules for gates
- Status: ACCEPTED (2026-11-20, L0)
- Context: `NOT_APPLICABLE` did not block, so a gate whose blocking checks were
  all skipped could PASS; a blocking command declared `missing:
  NOT_APPLICABLE` was also silently skipped.
- Decision:
  1. A blocking check may never resolve to `NOT_APPLICABLE`; a missing blocking
     command is `BLOCKED`.
  2. A gate with blocking checks but zero applicable blocking checks is
     `NOT_PROVEN`.
  3. A gate without blocking checks must declare `applicability: informative`
     to PASS; otherwise it is `NOT_PROVEN` (misconfiguration cannot pass).
- Consequences: informative gates stay possible but explicit; security exits
  cannot be satisfied by configuration gaps.
- Risks: a project that intentionally configured a blocking command as
  `NOT_APPLICABLE` must now remove the command or set `blocking: false`.
- Compatibility: the six check types and status precedence are unchanged.
- Tests: `tests/workflow.test.mjs` (blocking N/A, no blocking check,
  informative gate).

## DEC-017 — Evidence chain verification is complete and never repairs itself
- Status: ACCEPTED (2026-11-20, L0)
- Context: `evidence verify` checked each file hash only; `previous` links,
  ordering, index divergence, duplicates and unindexed files were not checked.
- Decision: `verifyEvidenceChain` verifies every hash, every `previous` link,
  chronological order, `index.lastHash`, duplicate ids/files, missing files and
  unindexed records. `doctor` and `evidence verify` use it. The function only
  reports; it never rewrites history.
- Consequences: `evidence verify --json` keeps `checked` and `bad` and adds
  `ok` and `problems` (additive). Exit 1 on any problem.
- Risks: legacy chains with a hand-edited index now fail instead of passing.
  That is the intended honesty.
- Compatibility: existing record files are unchanged.
- Tests: `tests/evidence.test.mjs`, `tests/doctor.test.mjs`.

## DEC-018 — Declared approval is not authentication
- Status: ACCEPTED (2026-11-20, L0), signature mechanism PROPOSED
- Context: `--human --by "name"` wrote `humanApproval: true` and gates accepted
  it. The agent can run the same command, so this is an identity declaration,
  not proof of human presence.
- Decision:
  1. Evidence now carries `approval { declaredBy, declaredAt, authenticated,
     method, keyId }`; `--human` always records `authenticated: false` and
     `method: declared`. `approvedBy` is preserved as historical declared
     identity.
  2. `security.requireAuthenticatedApproval` (additive, optional, default
     `false`) makes human-required gates reject declared-only approval as
     `NOT_PROVEN`.
  3. No signing key is generated or read. A verifiable signature scheme is
     proposed for validation; until then authentication is `NOT_PROVEN`.
- Consequences: default installs remain compatible and `doctor` reports
  declared-only approvals as `WARN`; strict projects can opt in now.
- Risks: enabling the flag without an external signing process blocks security
  and release exits. That is intentional and must be documented.
- Compatibility: old configs without the flag validate and behave as before.
- Tests: `tests/evidence.test.mjs`, `tests/workflow.test.mjs`,
  `tests/doctor.test.mjs`.

## DEC-019 — L0 packaging, demo and CI are real, not decorative
- Status: ACCEPTED (2026-11-20, L0)
- Context: `package.json.files` omitted `INSTALLATION.md`,
  `UTILISATION.md`, `QODER.md` and `CHANGELOG.md` although bootstrap copies
  them; `npm run demo` pointed to a missing file; there was no CI.
- Decision:
  1. Ship the four guides and `demo/` in the npm tarball.
  2. Implement `demo/run-demo.mjs` as a real temporary-project run
     (install → doctor → context → gate → resources); no predefined output.
  3. Add a GitHub Actions workflow (syntax, tests, demo, diagnostics).
  4. Add a packaging test that runs `npm pack`, inspects the tarball, installs
     it in a temporary prefix, runs `--version`, `init` and `doctor`.
- Consequences: the package is testable as installed, not only from the repo.
- Risks: CI is unproven until the repository is under Git and the workflow runs
  (the workspace is not a Git repository). This remains `NOT_PROVEN`.
- Tests: `tests/packaging.test.mjs`, `demo/run-demo.mjs`.

## REVISIT list
- Qoder project-vs-user skill precedence: docs conflict; AIDF links installs
  into `.qoder/skills` only and never relies on resolution order.
- A `qoder` marketplace/registry adapter (auto-download of AIDF skills) was
  rejected for 0.1.0: no network installs without human authorization.
