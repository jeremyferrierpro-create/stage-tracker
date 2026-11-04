# AIDF Architecture

Status: VALIDATED
Version: 0.1.0
Updated: 2026-10-31

## 1. Design goals and non-goals

AIDF is a governance layer installed *inside a project*, not a platform:

- It adapts to the host project (stack detected at install time, no architecture
  is imposed).
- It never replaces the IDE, an existing cockpit, or project-owned files.
- Every file it writes is recorded in `.aidf/manifest.json` with a SHA-256.
- Every executable claim needs recorded evidence; otherwise the status is
  `NOT_PROVEN`/`BLOCKED`.

Non-goals: package management, remote push, CI hosting, UI dashboards, and
secret handling (secrets are never read, only their filenames are listed).

## 2. Runtime model

- **Pure Node.js ESM, zero runtime dependencies** (rationale: DEC-006).
- Node >= 20.11 (tested on 24.21.0).
- YAML is handled by a small subset parser/dumper (`src/util/yaml.mjs`) that is
  covered by tests; registries are YAML for human editing, machine state is JSON.

## 3. Module map

```
bin/aidf.mjs ──► src/cli.mjs (dispatcher, global flags, exit codes)
                    ├─ src/commands/*.mjs        (one file per command)
                    ├─ src/core/paths.mjs        (canonical layout + vocabularies)
                    ├─ src/core/config.mjs       (config schema + document statuses)
                    ├─ src/core/manifest.mjs     (ownership records)
                    ├─ src/core/installer.mjs    (action engine: write/copy/mkdir/
                    │                             managed-block/merge-json/junction,
                    │                             conflict + backup + dry-run)
                    ├─ src/core/bootstrap.mjs    (core install plan + launcher)
                    ├─ src/core/detect.mjs       (environment + project inventory)
                    ├─ src/core/skills.mjs       (discovery, validation, dependency
                    │                             resolution, statuses, linking)
                    ├─ src/core/resources.mjs    (external resource inspection/lock)
                    ├─ src/core/workflow.mjs     (phases, gates, command runner)
                    ├─ src/core/evidence.mjs     (hash-chained evidence)
                    ├─ src/core/context.mjs      (task → documents + skills selection)
                    ├─ src/core/speckit.mjs      (Spec Kit status/init/sync)
                    ├─ src/core/adapters/{base,qoder,generic}.mjs (IDE adapters)
                    └─ src/util/{args,fsx,frontmatter,logger,text,yaml}.mjs
assets/
  adapters/qoder/   rules + orchestrator templates
  core/             AIDF.md + AGENTS fragment
  docs/             11 project document templates
  hooks/            session-context.mjs, audit.mjs
  integrations/     spec-kit mapping.yaml
  knowledge/        seeded rules (api, sql, typescript, testing, owasp baseline)
  registry/         skills.yaml, dependencies.yaml, activation-map.yaml, sources.yaml
  skills/           13 first-party SKILL.md
  workflows/        phases.yaml, gates.yaml
tests/              node:test suites (unit + CLI end-to-end)
```

## 4. Install layout inside a project

```
AGENTS.md                  managed block (user content preserved)
.qoder/rules/aidf-*.md     adapter rules (qoder)
.qoder/skills/aidf-*/      adapter skills (qoder)
.qoder/agents/             orchestrator (qoder)
.qoder/settings.json       hook entries merged, existing keys kept
.aidf/AIDF.md              agent entry point
.aidf/config.json          project + documents + security configuration
.aidf/manifest.json        ownership, hashes, backups, history, links
.aidf/bin/aidf.mjs         launcher → .aidf/framework/src/cli.mjs
.aidf/framework/           self-contained copy (update by re-running a newer dist)
.aidf/docs/                11 documents with statuses
.aidf/registry/            skills.yaml, activation-map, dependencies, resources.lock
.aidf/workflows/           phases.yaml + gates.yaml (project-editable copies)
.aidf/evidence/            evidence records + index.json + hash chain
.aidf/state/               workflow.json, context snapshots
.aidf/knowledge/           curated rules + sources/index.yaml
.aidf/reports/             SCAN.md, install.md
.aidf/backups/             kept before overwriting managed files
.aidf/logs/                local-only JSONL command logs (.gitignore'd)
.aidf-uninstall-backup/    created only when uninstall --force removes edited files
```

## 5. Action engine contract (`Installer`)

Actions: `mkdir`, `write`, `copy`, `managed-block`, `merge-json`, `junction`.
Status vocabulary: `CREATED`, `UPDATED`, `UNCHANGED`, `CONFLICT`, `LINKED`,
`FAILED`, and `WOULD_*` in dry-run. Invariants:

- A file not recorded in the manifest is never overwritten (`CONFLICT`).
- A manifest-recorded file modified locally is never overwritten (`CONFLICT`)
  unless `--force`, which keeps a backup in `.aidf/backups/<timestamp>/`.
- Dry-run writes nothing (including logs).
- `merge-json` unions arrays and keeps existing keys.
- `managed-block` replaces only the exact AIDF block region.
- Paths go through `resolveInside` (rejects traversal, absolute paths, reserved
  Windows names, and invalid characters).

## 6. Workflow and gates

Phases: `intake → discovery → specification → planning → implementation →
testing → security → release`. Each phase has `<phase>-entry` and
`<phase>-exit` gates in `assets/workflows/gates.yaml` with typed checks:

`file-exists`, `doc-status`, `evidence`, `command`, `previous-gate`, `manual`.

Status precedence in a gate: `FAIL > BLOCKED > NOT_PROVEN > PASS`;
`NOT_APPLICABLE` never blocks. Command checks execute only with `--allow-exec`
or `security.allowCommandExecution=true`, never through a shell
(`shell: false`, no operator allowed), and they record stdout/stderr, exit code
and duration in evidence.

## 7. Evidence and context

- Evidence records are JSON with `sha256` over their body, chained with
  `previous = lastHash`; `aidf evidence verify` re-verifies every record.
- `doc-status`/gate statuses can require `humanApproval`; human records require
  explicit `--by "<name>"`.
- The context assembler scores documents and skills from `activation-map.yaml`
  (always-on, phase, keywords) and merges the project registry; contradiction-
  sensitive documents that are `BLOCKED`/`OUTDATED` set the context status to
  `BLOCKED` and exit code 2.

## 8. Extensibility

- New adapters: implement `plan/verify/detect` and register in
  `src/core/adapters/index.mjs`; the CLI and registry code are adapter-agnostic
  (`ide` field in `skills.yaml` filters compatibility).
- New skills: add `assets/skills/<name>/SKILL.md` + `assets/registry/skills.yaml`
  entry + dependency entry; discovery, validation and statuses are generic.
- New external resources: add a `sources.yaml` entry; `aidf setup resources`
  writes a reproducible lock file (`resources.lock.json`) with commit, tree
  hash and licence decision.
