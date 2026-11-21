# ADR-006 — CLI Commands

- Status: **proposed**
- Date: 2026-11-20
- Deciders: AIDF maintainer
- Reference: AIDF 0.1.0 baseline (commit 85f187a)

## Context

AIDF 0.1.0 has 18 CLI commands:

```text
init, install, update, uninstall, setup, resources, doctor, scan,
skills, workflow, validate, evidence, docs, context, speckit, config, version, help
```

L1 introduces canonical JSON artefacts and new features that require CLI commands. The question is: add new commands or extend existing ones?

## Decision

**Minimize new commands**. Prefer extending existing commands when the functionality fits their scope. Add new commands only when:

1. The scope is distinct and large enough to justify a new command
2. The command would otherwise grow too complex
3. The command has a clear user-facing workflow

### Command matrix

| Feature | Command | Reason |
|---|---|---|---|
| List requirements | `requirements list` | New command (large scope) |
| Show requirement | `requirements show <id>` | New command |
| Add requirement | `requirements add` | New command |
| Update requirement | `requirements update <id>` | New command |
| Delete requirement | `requirements delete <id>` | New command |
| Link to tests | `requirements link <id>` | New command |
| Compute coverage | `requirements coverage` | New command |
| Validate artefact | `artefacts validate` | New command |
| List artefacts | `artefacts list` | New command |
| Generate Markdown | `docs generate` | Extend `docs` |
| Detect divergence | `docs check` | Extend `docs` |
| Regenerate Markdown | `docs regenerate` | Extend `docs` |
| Traceability report | `traceability report` | New command |
| Missing links | `traceability missing` | New command |
| Verify chain | `traceability verify <id>` | New command |

### Proposed new commands

#### `artefacts` (new command)

Manage canonical artefacts (requirements, decisions, test-plan, etc.)

```bash
# List all artefacts
aidf artefacts list [--json]

# Show artefact details
aidf artefacts show <id> [--json]

# Validate artefact
aidf artefacts validate <path> [--json]

# Validate all artefacts
aidf artefacts validate-all [--json]
```

#### `requirements` (new command)

Manage requirements registry

```bash
# List requirements
aidf requirements list [--type F|NF] [--status <status>] [--json]

# Show requirement
aidf requirements show <id> [--json]

# Add requirement
aidf requirements add --id <id> --type F|NF --title <title> --desc <desc> [--priority must|should|could] [--acceptance <criterion>] [--link <targetId>]

# Update requirement
aidf requirements update <id> [--title <new>] [--desc <new>] [--acceptance <new>] [--link <add|remove> <targetId>]

# Delete requirement (mark BLOCKED)
aidf requirements delete <id> [--yes]

# Link to tests
aidf requirements link <id> --test <path>#<test-name> [--type verified-by|implemented-by]

# Compute coverage
aidf requirements coverage [--json]

# Validate registry
aidf requirements validate [--json]
```

#### `traceability` (new command)

Manage and verify traceability chain

```bash
# Report all broken chains
aidf traceability report [--json]

# Find requirements without design
aidf traceability missing --type design [--json]

# Find tests without evidence
aidf traceability missing --type evidence [--json]

# Verify chain for artefact
aidf traceability verify <id> [--json]
```

#### `docs` (extend existing command)

Add Markdown generation and divergence detection

```bash
# Existing: list documents
aidf docs list [--json]

# Existing: change document status
aidf docs status <doc> <status> [--by <name>] [--note <note>]

# NEW: generate JSON from Markdown
aidf docs generate <doc> [--json]

# NEW: detect divergences
aidf docs check [--json]

# NEW: regenerate Markdown (overwrites manual changes)
aidf docs regenerate <doc> [--yes]

# NEW: reconcile frozen document (requires approval)
aidf docs reconcile <doc> --by <name> [--note <note>]
```

### Exit codes

| Code | Meaning |
|---|---|
| `0` | Success |
| `1` | Failure/blocked (missing required parameter, artefact not found, invalid schema, etc.) |
| `2` | Conflict or contradiction (human approval required, frozen document, divergence, etc.) |

### `--json` support

All new commands support `--json` for machine-readable output.

### `--human --by <name>` support

Commands that modify artefacts support human accountability:

```bash
# Regenerate Markdown requires human approval
aidf docs regenerate PRD --by "Jeremy"

# Delete requirement requires human approval
aidf requirements delete REQ-F-001 --by "Jeremy" --yes
```

## Consequences

### Positive

- Minimal new commands (only when scope justifies)
- Consistent with existing command patterns
- `--json` and `--human` support everywhere
- Exit codes preserved

### Negative

- New commands to learn
- CLI grows slightly

### Neutral

- Commands are additive, not replacing existing ones
- Existing commands unchanged

## Status

**proposed** — awaiting human validation before implementation.

## Next steps

- If approved: implement `artefacts`, `requirements`, `traceability`, extended `docs` commands
- Tests required: command parsing, execution, JSON output, human approval