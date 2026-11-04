# AIDF Known Limitations

Status: VALIDATED (documented at release 0.1.0)

## 1. Qoder runtime behaviour (cannot be proven from the filesystem)

- Rule activation type (Always Apply / Model Decision / Specific Files / Apply
  Manually) is a UI setting. AIDF reports it `NOT_PROVEN` until a human sets it
  and records evidence.
- Runtime skill discovery (`/skills`) and hook execution need a live Qoder
  session; AIDF cannot verify them off-line.
- Qoder documentation is inconsistent on project-vs-user skill precedence;
  AIDF installs only at project scope and never depends on the order.

## 2. Scope constraints of 0.1.0

- No plugin marketplace, no remote registry, no auto-download: all external
  resources are local clones or references, gated by licences.
- No remote push/publish, no migrations, no package installs — these are
  project responsibilities; AIDF only stages/records commands.
- One adapter pair only (`qoder`, `generic`); other IDE adapters are future
  work (interfaces exist: `plan/verify/detect`).
- `generic` adapter is AGENTS.md-block-only: rules/skills/agents require Qoder.

## 3. YAML parser is a subset

- No anchors/aliases, multi-document streams, flow maps, or exotic scalars.
  AIDF's own registries are covered; arbitrary third-party YAML may fail loudly
  (by design; the error names the line).

## 4. Spec Kit specifics

- `specify init` no-ops/blocks when `.specify/` already exists; AIDF surfaces
  the status instead of reinitialising.
- On a non-empty project, init requires `--specify-force` (explicit).
- A pre-existing (external) constitution blocks `sync` forever until the human
  merges or moves it — intentional, but a manual step.
- Version convergence is not assumed: source clone (1.2.6.dev0) may be newer
  than the CLI (1.2.3); both are recorded in the lock/report.

## 5. Windows behaviour

- Junctions are used instead of symlinks (no privilege required); on
  non-Windows the same code path uses directory symlinks.
- `npm.ps1` is blocked by the host's execution policy; irrelevant to AIDF
  (no runtime dependencies) but affects developing AIDF with npm scripts —
  use `cmd /c npm` or call `node` directly.
- Reserved Windows names in any generated path are rejected outright.

## 6. Documentation Eisenhower

- Document templates are intentionally `[TO FILL]`: AIDF generates honest
  drafts with statuses `DRAFT`; it never invents requirements, metrics or
  personas. Completing them is project work.

## 7. Evidence model

- Evidence proves *that* something ran / was approved, not the semantic
  quality of the artefact. Gate PASS relies on the declared checks; a
  malicious agent could record a fake but hash-consistent manual record —
  mitigated by `--human --by` requirements and the `previous`-hash chain being
  committed (detectable tampering, not authentication).
- No cryptography beyond SHA-256; no signing infrastructure in 0.1.0.

## 8. Multi-root workspaces

- AIDF resolves one project root (walk-up to nearest `.aidf/manifest.json`).
  Multi-root IDE setups should install AIDF per root; sharing one install
  across roots is not supported.
