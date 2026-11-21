# ADR-001 — Structured Artefacts Architecture

- Status: **proposed**
- Date: 2026-11-20
- Deciders: AIDF maintainer
- Reference: AIDF 0.1.0 baseline (commit 85f187a)

## Context

AIDF 0.1.0 uses Markdown documents as the primary source of project artefacts (PRD, TRD, UI-UX, SECURITY, etc.). These documents are useful for human consumption but lack:

- A machine-readable structure for validation
- Stable identifiers for traceability
- Explicit relationships between requirements, design, implementation and tests
- A mechanism to verify that a "covered" requirement actually has executable tests

The project needs a structured artefact system that:

1. Preserves Markdown documents for human readability
2. Adds a machine-readable layer for validation and traceability
3. Connects requirements → design → implementation → tests → evidence
4. Prevents虚假 declarations of coverage without real execution
5. Works within the existing AIDF workflow and CLI

## Decision

Introduce a layered artefact model:

| Layer | Format | Purpose | Read/Write | Source of Truth |
|---|---|---|---|---|
| **Canonical** | JSON | Machine validation, linking, traceability | CLI-only writes | JSON files |
| **Documentary** | Markdown | Human readability, review | Read-only (derived) | JSON |
| **Generated** | Markdown | Display in IDE, CLI | Read-only (derived) | JSON |

### Artefact types

AIDF will manage the following canonical artefacts:

```text
aidf/
  artefacts/
    discovery.json          # Project facts, constraints, resources
    constraints.json        # Non-negotiable limits (tech stack, budget, deadlines)
    requirements.json       # Requirement registry
    architecture.json       # Architecture decisions and component model
    test-plan.json          # Test strategy and test map
    decisions/
      <decision-id>.json    # ADRs (Architectural Decision Records)
    security/
      threat-model.json     # STRIDE-based threat model
```

### Envelope contract (common schema)

Every artefact shares a common envelope:

```json
{
  "id": "stable-identifier",
  "schemaVersion": "1.0.0",
  "createdAt": "2026-11-20T...",
  "updatedAt": "2026-11-20T...",
  "status": "DRAFT"|"VALIDATED"|"BLOCKED"|"OUTDATED",
  "source": {
    "type": "human"|"generated"|"derived",
    "author": "Jeremy"|"aidf",
    "createdAt": "2026-11-20T...",
    "generator": null|"schema-name@1.0.0"
  },
  "links": [
    {
      "targetId": "REQ-F-001",
      "targetType": "requirement",
      "type": "implements"|"designed-by"|"tested-by"|"verified-by"
    }
  ],
  "validation": {
    "schemaVersion": "1.0.0",
    "timestamp": "2026-11-20T...",
    "valid": true,
    "errors": [],
    "warnings": []
  }
}
```

### Rationale

- **id**: Stable identifier that survives reordering, editing, migration
- **schemaVersion**: Independent of AIDF version; enables schema evolution
- **createdAt/updatedAt**: Immutable creation date, mutable update date
- **status**: Reuse existing AIDF statuses (DRAFT, VALIDATED, BLOCKED, OUTDATED)
- **source**: Provenance tracking; distinguishes human vs generated content
- **links**: Explicit relationships (requirement → design → implementation)
- **validation**: Real-time validation result, not just declaration

## Consequences

### Positive

- Artefacts are machine-validated (schema, types, links)
- Traceability chain is verifiable (requirement → evidence)
- Coverage calculation is deterministic (no false PASS)
- Markdown remains human-readable (derived view)
- No breaking change to existing CLI or workflow

### Negative

- More files to manage in `.aidf/`
- Initial migration of existing Markdown documents
- CLI commands required for artefact manipulation

### Neutral

- artefacts are stored under `.aidf/artefacts/` (not replacing `.aidf/docs/`)
- Markdown documents remain under `.aidf/docs/` (read-only, generated)

## Status

**proposed** — awaiting human validation before implementation.

## Next steps

- L1-A design complete; ready for L1-B implementation (schemas and registry)
- If approved: implement JSON schemas, registry, validation, tests
- If rejected: retain current Markdown-only model, re-evaluate requirements