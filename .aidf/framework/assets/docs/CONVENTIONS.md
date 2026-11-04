---
doc: CONVENTIONS
status: DRAFT
version: 0.1.0
source: aidf-template
---

# Development Conventions

> **Status: DRAFT.** Conventions must reflect the repository's actual tooling.
> Where the repository already enforces a rule (lint, format, CI), point to the
> configuration instead of restating it.

## 1. Repository structure

```text
[TO FILL: real top-level structure with responsibilities]
```

Rules:
- New code goes in the module that owns the concern; do not create parallel trees.
- Generated artefacts are not edited by hand.
- `[TO FILL]`

## 2. Naming

| Element | Convention | Example | Enforced by |
|---|---|---|---|
| Files | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` |
| Directories | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` |
| Types/classes | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` |
| Functions/variables | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` |
| Tests | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` |
| Branches | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` |

## 3. Language and framework rules

### TypeScript / JavaScript
- `strict` mode: `[TO FILL: check tsconfig]`
- Module system: `[TO FILL]`
- Forbidden patterns: `[TO FILL: e.g. any without justification]`
- Error handling: `[TO FILL]`

### Other languages
- `[TO FILL]`

## 4. API and database conventions

- Endpoint naming: `[TO FILL]`
- Status codes and error shape: `[TO FILL]`
- Migration naming and reversibility: `[TO FILL]`
- Query and index review: `[TO FILL]`

## 5. Testing conventions

- Test levels: unit / integration / contract / e2e
- Location: `[TO FILL]`
- Naming: `[TO FILL]`
- Determinism: no network or clock dependence unless explicitly controlled
- Required coverage for changed behaviour: `[TO FILL]`
- Test command: `[TO FILL]`
- A test that was not executed is `NOT_PROVEN`, never `PASS`.

## 6. Git conventions

- Branch model: `[TO FILL]`
- Commit format: `[TO FILL]`
- Review requirements: `[TO FILL]`
- Never commit secrets, generated credentials, or local environment files.

## 7. Documentation conventions

- Documents carry a status frontmatter (`DRAFT`, `VALIDATED`, `BLOCKED`, `OUTDATED`, `NOT_APPLICABLE`).
- Decisions are recorded in `DECISIONS.md`; document changes in `CHANGELOG.md`.
- Claims link to evidence (command, test, file, decision).

## 8. Definition of Done

A change is done when:
1. behaviour is specified and accepted;
2. implementation is complete and reviewed;
3. relevant tests pass and the command is recorded;
4. security implications are reviewed;
5. documentation and changelog are updated;
6. evidence is stored in `.aidf/evidence/`;
7. the phase exit gate is `PASS`.

## 9. Tooling

| Tool | Purpose | Command | Config |
|---|---|---|---|
| `[TO FILL]` | lint | `[TO FILL]` | `[TO FILL]` |
| `[TO FILL]` | format | `[TO FILL]` | `[TO FILL]` |
| `[TO FILL]` | test | `[TO FILL]` | `[TO FILL]` |
| `[TO FILL]` | build | `[TO FILL]` | `[TO FILL]` |
