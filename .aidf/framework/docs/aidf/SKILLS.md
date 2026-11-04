# AIDF Skills

Status: VALIDATED

## 1. Status vocabulary and lifecycle

```
DISCOVERED  file found on disk by the discovery walk
VALIDATED   frontmatter and body pass Qoder constraints
REGISTERED  present in .aidf/registry/skills.yaml
AVAILABLE   VALIDATED and all dependencies AVAILABLE
BLOCKED     file missing, duplicate, or dependencies not ready
INCOMPATIBLE frontmatter invalid or adapter not supported
```

Constraints enforced (Qoder documented limits): `name` matches
`^[a-z0-9][a-z0-9-]{0,63}$`, `description` ≤ 1024 chars, frontmatter required,
directory name should match `name`.

## 2. First-party catalog (installed by the qoder adapter)

| id | skill | phases | depends on |
|---|---|---|---|
| project-context | aidf-project-context | intake, discovery | — |
| product-analysis | aidf-product-analysis | intake, specification | project-context |
| requirements-engineering | aidf-requirements-engineering | specification, planning | product-analysis |
| architecture-review | aidf-architecture-review | discovery, planning | project-context |
| database-design | aidf-database-design | planning, implementation | architecture-review |
| ui-ux-implementation | aidf-ui-ux-implementation | specification, planning | requirements-engineering |
| frontend-implementation | aidf-frontend-implementation | implementation | ui-ux-implementation |
| backend-implementation | aidf-backend-implementation | implementation | architecture-review |
| testing-validation | aidf-testing-validation | implementation, testing | requirements-engineering |
| security-review | aidf-security-review | security, release | testing-validation |
| devops-engineering | aidf-devops-engineering | implementation, release | architecture-review |
| documentation-maintenance | aidf-documentation-maintenance | implementation, release | project-context |
| phase-transition | aidf-phase-transition | all | project-context, testing-validation |

Each SKILL.md carries: purpose, trigger list, documents it may update, hard
rules (e.g. "no PASS without evidence", "real tests, not mocks"), and
deliverables. Source files: `assets/skills/<name>/SKILL.md`.

## 3. Context selection (how skills are picked)

`assets/registry/activation-map.yaml` defines three layers:

1. **always-on**: `constitution` + `CONVENTIONS` documents, `project-context`
   skill.
2. **by phase**: the phase's `requiredDocuments` and `skills` (from
   `assets/workflows/phases.yaml`) with weight 3.
3. **by keyword**: 10 rules (api, database, ui, security, test, ci, refactor,
   bug, docs, performance) with weight 2.

The assembler merges by id, sorts by priority, and caps (`context.maxSkills`,
default 5). If a contradiction-sensitive document (`SECURITY`, `TRD`,
`BACKEND`, `PRD`) is `BLOCKED` or `OUTDATED`, the whole context is `BLOCKED`.

## 4. External skills

External skill locations are scanned read-only (`src/core/skills.mjs`):

- project `.qoder/skills`, `.aidf/skills`, `.claude/skills`, `.agent(s)/skills`,
- user `~/.qoder/skills`,
- locally cloned sources registered in `assets/registry/sources.yaml`
  (mattpocock-skills, superpowers).

They are indexed (id, name, path, hash, status) and never copied. `aidf skills
link <id>` creates a junction into `.qoder/skills` for a specific external
skill when the IDE cannot see it. Duplicate names across roots are `BLOCKED`
rather than resolved silently.

## 5. Adding a first-party skill

1. Create `assets/skills/<aidf-name>/SKILL.md` (frontmatter + body).
2. Add an entry in `assets/registry/skills.yaml` (id, description, phases,
   triggers, `ide` compatibility).
3. If it depends on others, add an entry in `assets/registry/dependencies.yaml`.
4. Run `node --test "tests/*.test.mjs"`; `skills validate` must report all
   13 + 1 skills AVAILABLE with no cycles.
