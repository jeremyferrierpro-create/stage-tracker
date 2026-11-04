# AIDF ↔ GitHub Spec Kit

Status: VALIDATED against specify CLI 1.2.3 and spec-kit source 1.2.6.dev0

## 1. Ownership model

AIDF owns **product-wide durable truth** (constitution, PRD, TRD, SECURITY,
CONVENTIONS, DECISIONS). Spec Kit owns **feature-level change artefacts**
(`specs/<feature>/spec.md`, `plan.md`, `tasks.md`). Each fact lives in exactly
one place; feature specs reference `.aidf/docs/PRD.md` requirement IDs (FR-…)
instead of restating them.

## 2. Verified integration facts (2026-10-31)

- `specify init --here --integration qodercli --ignore-agent-tools
  --non-interactive` succeeds in an empty directory (exit 0).
- It creates `.specify/` (`templates/`, `scripts/`, `scripts/powershell/`,
  `memory/constitution.md`, `workflows/`, `init-options.json`,
  `integrations/qodercli.manifest.json`) **without** touching `.aidf/`,
  `AGENTS.md` or `.qoder/` content.
- It writes `.qoder/skills/speckit-*` (10 skills with hyphen names). The
  documented `speckit.` (dot) variant also existed historically; both are
  accepted by `specKitStatus`.
- In a directory that already contains AIDF, the same init fails with
  “Current directory is not empty … Re-run with --force to merge into it”.

## 3. AIDF command mapping

| AIDF command | What it runs / does |
|---|---|
| `aidf speckit status` | Reports `.specify` presence, templates, constitution sync state, detected `speckit-*` skills, CLI path/version. |
| `aidf speckit init` | Runs the init command above. Requires `--allow-exec`. If the project is not empty it is `BLOCKED` until `--specify-force` is passed (explicit opt-in for `--force`). |
| `aidf speckit sync --adopt` | Publishes the AIDF constitution as a generated copy: `<!-- AIDF:SOURCE-HASH:<sha256> -->` + body of `.aidf/docs/constitution.md`. |
| `aidf speckit sync` | Refreshes the copy when the source hash changed; reports `UNCHANGED` otherwise. |

Constitution sync states: `IN_SYNC`, `OUTDATED`, `EXTERNAL` (a constitution
without the AIDF marker), `NOT_APPLICABLE` (absent).

## 4. Conflict rule (verified)

If `.specify/memory/constitution.md` exists without the AIDF hash marker
(e.g. freshly created by `specify init`, or customised by the team), `aidf
speckit sync` reports `CONFLICT` (exit 2) and never overwrites it. The human
must merge manually, move the file aside, or delete it knowingly.

## 5. Document mapping (also written to `.specify/aidf/mapping.md`)

| AIDF document | Spec Kit artefact | Owner |
|---|---|---|
| constitution | `.specify/memory/constitution.md` | AIDF (copy-with-hash) |
| PRD | `specs/<feature>/spec.md` (requirements reference) | shared |
| TRD | `specs/<feature>/plan.md` | shared |
| APPFLOW | `specs/<feature>/spec.md` scenarios | shared |
| UI-UX | `specs/<feature>/design/` | shared |
| BACKEND | `specs/<feature>/contracts/` | shared |
| PLAN | `specs/<feature>/tasks.md` | shared |
| SECURITY | `specs/<feature>/checklists/security.md` | AIDF |
| CONVENTIONS | constitution/agent instructions | AIDF |

## 6. How the two systems interlock

- AIDF gates are phase-level and durable; Spec Kit `/speckit-*` commands are
  feature-level commands an agent may run during a feature.
- `implementation-exit` evidence can capture the same diff that a feature task
  list tracks; no duplicated status fields.
- The generated constitution copy keeps the two tools from diverging on
  project principles, with a verifiable source hash.
