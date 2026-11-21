# Changelog

All notable changes to AIDF are documented here. This project does not use
conventional commits; each entry names real, verified behaviour changes.

## [0.3.2] — 2026-11-21

### Changed (docs)
- `README.md`, `INSTALLATION.md`, `UTILISATION.md` aligned with the real
  0.3.x state: npm package `framework-aidf`, three adapters with honest
  runtime statuses (OpenCode PROVEN, Qoder skills PROVEN-partiel,
  generic PROVEN), all 27 commands documented including the L1–L3
  engines, `aidf ops` pipeline + `ops watch`, real Figma REST client,
  resource engine v2 commands, updated file counts and test totals
  (217/217).

## [0.3.1] — 2026-11-21

### Added (verified 2026-11-21)
- `aidf ops watch` — continuous monitoring: `pipeline.monitoring.steps`
  (same step schema, typically `kind: verify`) run in bounded cycles via
  `--cycles`/`--duration`/`--interval`; same `--allow-exec` permission
  model, stops on first non-PASS, writes `OPS-WATCH-*` reports and
  `ops-watch` evidence. Verified by `tests/ops.test.mjs`.
- Real Figma REST client in `figma/validators.mjs` (replaces the mock):
  `GET /v1/me` and `GET /v1/files/:id` against api.figma.com with the
  token from `figma.apiKey`/`figma.token` or `FIGMA_TOKEN`. Verified live:
  the supplied token resolves to a real Figma identity and a real file
  (`validateFigma` → `fully_verified`). Visual parity capture remains
  NOT_PROVEN.

### Changed
- Figma unit tests rewritten against a stubbed `fetch` (no network in CI).

## [0.3.0] — 2026-11-21

### Added (verified 2026-11-21)
- `aidf ops` — project delivery pipeline engine (`src/core/ops.mjs`,
  `src/commands/ops.mjs`). `.aidf/workflows/pipeline.yaml` (project-owned,
  scaffolded by `aidf ops init`) declares ordered steps
  (`build`/`test`/`deploy`/`verify`/`custom`) and rollback commands.
  `aidf ops run` executes steps sequentially via the same safe
  `runCommand` used by gates (no shell operators), stops on the first
  non-PASS step, writes `.aidf/reports/OPS-*.{json,md}` and records the
  last successful deploy for `aidf ops rollback`. Execution requires
  `--allow-exec` or `security.allowCommandExecution=true`; without it every
  step reports NOT_PROVEN and nothing runs. Verified by
  `tests/ops.test.mjs` (init/plan dry-run, denied vs permitted execution,
  fail-fast, shell-operator rejection, rollback).

## [0.2.0] — 2026-11-21

AIDF 0.2.0 turns the 0.1.0 governance base into a full engineering framework:
canonical artefacts with traceability, a deterministic technology decision
engine, design workflows with fallback mockups, resource and third-party
skill management, an OpenCode adapter with real runtime proof, a STRIDE
threat-model generator, test planning, generated documentation and a
release readiness report. Migration from 0.1.0 is additive and tested.

### Added (verified 2026-11-21)
- Resource Engine v2: `aidf resources inventory|coverage|reconcile` produce a
  file-level sha256 manifest, a licence policy per resource, duplicate-skill
  detection and lock/manifest drift reconciliation. Verified on the real
  `resources/` corpus: 18 resources, 12 533 files, 81 SKILL.md (74 unique
  AVAILABLE, 7 DUPLICATE), 23 secret-like paths skipped.
- Skills Engine v2: `aidf skills sources|catalog` builds the third-party skill
  catalogue with licence-derived statuses; `aidf skills enable <id>` copies one
  canonical skill into the active adapter directory with an `AIDF-SOURCE.json`
  provenance file; executables and secrets are never copied unless
  `--allow-scripts` is given. Verified by a real copy of `owasp-security`.
- OpenCode adapter: `aidf install --adapter opencode` writes `AGENTS.md` and
  the 13 built-in skills under `.opencode/skills/`; verification is
  CONTRACT-PROVEN (runtime discovery NOT_PROVEN).
- Artefact schemas and validation (`src/core/artefacts`, `assets/schemas/`),
  requirements registry and traceability chain (L1-B/L1-C partial).
- Technology Decision Engine (deterministic analyzer + decision document
  generator; interpretation of trade-offs/risks is delegated to the
  invoking AI agent by design — `analysis.delegation`, no AIDF-owned model).
- Design Engineering engine (complexity classifier, per-complexity workflows,
  SVG/HTML fallback mockups; Figma integration NOT_PROVEN).
- Gap matrix: `docs/aidf/MATRICE-0.1.0-vers-0.2.0.md`.
- CLI commands exposing the L1/L2/L3 engines: `aidf artefacts
  list|show|validate|verify`, `aidf requirements
  list|show|add|update|delete|coverage`, `aidf traceability
  report|verify|missing|broken`, `aidf decision evaluate` (persists a DRAFT
  artefact under `.aidf/artefacts/decisions/`, human approval required) and
  `aidf design classify|run` (writes real SVG/CSS/Markdown fallback outputs).
- `aidf security analyze|show`: deterministic STRIDE threat model generated
  from project detection (assets, trust boundaries, threats grounded in
  detected surface), persisted as `TM-###` under
  `.aidf/artefacts/security/` with a Markdown report option.
- `aidf tests plan|coverage`: generates a `TP-###` test-plan artefact (one
  planned case per acceptance criterion, type inferred from wording) and
  reports requirement coverage across planned cases and `TEST-*` artefacts.
- `aidf docs generate [--check]`: renders canonical artefacts to Markdown
  under `.aidf/docs/generated/` with provenance headers and manual-edit
  divergence detection.
- `aidf release report [--record] [--strict]`: read-only release readiness
  report aggregating gate evaluations, document statuses, CHANGELOG,
  evidence and artefacts; writes `.aidf/reports/RELEASE-<date>.{json,md}`.
- Artefact discovery now recurses into subdirectories (`decisions/`,
  `security/`), so nested artefacts appear in `artefacts list`, link
  resolution and traceability; `createLink` writes back to the real path.
- Migration 0.1.0 → 0.2.0 verified two ways: `tests/migration.test.mjs`
  (legacy fixture upgrades additively, project files preserved, engines
  usable) and a manual run against a real 0.1.0 install copy
  (148 → 198 managed files, 0 conflicts, `doctor` adapter/skills PASS).
- OpenCode runtime verification (2026-11-21): a real `opencode run` session
  quoted the AIDF instructions and listed the installed skills; `opencode
  debug skill` discovers project skills only inside a git worktree, so
  adapter verification now reports a `git-worktree` check (PARTIAL without
  `.git`).

### Removed
- `src/core/technology-decision/analyzer-llm.mjs` and the `AI_MODEL` /
  `callLLM` path: AIDF is a governance framework and owns no model. The
  decision document now records `analysis.delegation`
  (`delegatedTo: "invoking-agent"`) so interpretation stays with the AI
  agent that runs the command. Deterministic analysis is unchanged and
  remains the only proven path.

### Fixed (2026-11-21)
- `getAllArtefactIds` imported `../util/fsx.mjs` from `core/artefacts/`,
  silently resolving no artefact ids and marking every link unresolved.
- `findMissingLinks` searched link *values* for type names instead of
  resolving targets; it now matches resolved artefact types plus documented
  ID prefixes (`REQ-`, `DES-`, `BACKEND-`, `TEST-`, `EVID-`).
- `resources inspect` crashed on files larger than 2 GiB (whole-file
  `readFile`): hashing is now capped at 64 MiB with explicit `skipped`
  counters; secret-like paths are never opened.
- MODULES corpus exclusions extended to media/binaries (ISO, mp4, archives,
  installers) matching its documented course-document scope.
- Packaging test: GNU tar on Windows treated `C:\…` tarballs as remote hosts;
  `--force-local` and relative paths fix pack/list/extract/pack-install.
- Adapter registry test updated for the new `opencode` adapter.

Test suite: 211/211 PASS (`node --test "tests/**/*.test.mjs"`).

### Fixed
- npm packaging now ships `INSTALLATION.md`, `UTILISATION.md`, `QODER.md`,
  `CHANGELOG.md` and `demo/`; a packaging test packs, installs, runs
  `--version`, `init` and `doctor` in a temporary prefix.
- `npm run demo` now runs a real install/doctor/context/gate/resources flow in
  a temporary project; it never prints predefined results.
- `doc-status` reads the real document frontmatter and fails when it diverges
  from `config.json`; a missing document is `BLOCKED`.
- A blocking check can no longer be `NOT_APPLICABLE`; a gate with no applicable
  blocking check is `NOT_PROVEN`; a gate with no blocking check must declare
  `applicability: informative` to PASS.
- `evidence verify` now verifies the whole chain (hashes, `previous` links,
  order, `index.lastHash`, duplicates, missing and unindexed files) and never
  repairs history. `doctor` uses the same verification.

### Added
- Declared vs authenticated approval: `--human --by` records
  `approval.authenticated: false`; `security.requireAuthenticatedApproval`
  (optional, default false) rejects declared-only approval for human-required
  gates. Existing `approvedBy` is preserved.
- GitHub Actions CI (syntax, test suite, demo, diagnostics); CI execution is
  `NOT_PROVEN` until the repository is under Git.
- Tests for config, detect, context, resources, docs, evidence, doctor,
  speckit, packaging, and the new gate/approval semantics. 87 tests pass.

## [0.1.0] — 2026-10-31

### Added
- Core action engine (`Installer`): `write/copy/mkdir/managed-block/merge-json/
  junction` with manifest ownership, SHA-256 tracking, backups, conflict
  refusal, atomic writes and full dry-run support (`WOULD_*` statuses).
- Portable install layout: `.aidf/framework` self-contained copy, launcher
  `.aidf/bin/aidf.mjs`, docs/registry/workflows/knowledge/evidence/state, and
  idempotent re-installation.
- CLI commands with `--json` and the 0/1/2 exit contract: `install`, `init`,
  `update`, `uninstall`, `setup resources`, `resources list|inspect`, `doctor`,
  `scan`, `skills list|validate|show|refresh|link`, `workflow status|enter|exit|
  next|reset`, `validate --list|--phase|--gate`, `evidence list|show|add|
  capture|verify`, `docs list|show|status`, `context --task`, `speckit
  status|init|sync`, `config list|get|set|validate`, `help`, `version`.
- 11 project document templates with status frontmatter and explicit
  `[TO FILL]` honesty; `aidf docs status` updates frontmatter + manifest.
- Qoder adapter: 3 rules (00-core, 10-security, 20-context), 13 first-party
  skills, `aidf-orchestrator` agent, optional hooks with redacted audit and
  session context; verifier returning CONTRACT-PROVEN (files) vs NOT_PROVEN
  (runtime UI checks).
- Generic adapter: AGENTS.md managed block only.
- Phase workflow (8 phases, 16 gates) with typed checks (`file-exists`,
  `doc-status`, `evidence`, `command`, `previous-gate`, `manual`), command
  execution opt-in (`--allow-exec` or config), shell-free runner rejecting
  shell operators, output captured as evidence.
- Hash-chained evidence with human approvals (`--by`) and verification tooling.
- Context assembler with always-on/phase/keyword activation map, caps, and a
  BLOCKED status for contradiction-sensitive documents.
- Spec Kit integration: status/init/sync; qodercli init; explicit
  `--specify-force` for non-empty projects; copy-with-hash constitution sync
  (CONFLICT never overwrites Spec Kit content); mapping export to
  `.specify/aidf/mapping.md`.
- Resource registry + lock (`resources.lock.json`) with licence gating:
  unknown licence ⇒ BLOCKED, index-only.
- 43 tests (unit + CLI end-to-end) passing on Node 24.21.0 / Windows 11 x64.

### Fixed
- YAML dumper emitted invalid sequence-of-maps (first entry glued to the
  second); roundtrip now verified, including nested arrays and block scalars.
- `specKitStatus` now detects the real `speckit-*` (hyphen) skill dirs
  generated by specify 1.2.3's qodercli integration.
- `setup resources` resolution order (project → parent → framework parent →
  framework root) stops installed-copy internal paths (`.aidf/skills`)
  from shadowing real sibling clones.
- Dry-run installs no longer create `.aidf/logs` (no side effects at all).
- `Installer.apply` returns only the current batch's results (previously
  accumulated, breaking idempotency reporting in tests).
- `speckit init` against a non-empty (already installed) project reports
  BLOCKED with an actionable message instead of a raw specify failure.

### Known limitations
- Qoder rule activation and runtime skill discovery remain manual/human-evidenced
  (by design, not a bug).
- Spec Kit project-vs-user skill precedence in Qoder docs is contradictory;
  AIDF ignores driver-specific resolution order (project scope only).
