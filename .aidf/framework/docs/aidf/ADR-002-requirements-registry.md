# ADR-002 — Requirements Registry

- Status: **proposed**
- Date: 2026-11-20
- Deciders: AIDF maintainer
- Reference: AIDF 0.1.0 baseline (commit 85f187a)

## Context

AIDF 0.1.0 does not have a structured requirement registry. Requirements may be written in Markdown (PRD, BACKEND) but:

- No stable identifiers for requirements (IDs are implicit in position)
- No connection to design, implementation, tests or evidence
- No way to verify "coverage" without executing real tests
- No way to track which requirements are incomplete or untested

The project needs a requirement registry that:

1. Assigns stable identifiers (REQ-F-001, REQ-NF-001)
2. Connects requirements to design, tests and evidence
3. Computes coverage deterministically (not by declaration)
4. Distinguishes between "implemented" and "verified"

## Decision

Create a requirements registry as a canonical artefact:

```text
.aidf/artefacts/requirements.json
```

### Requirement schema

```json
{
  "id": "REQ-F-001",
  "schemaVersion": "1.0.0",
  "createdAt": "2026-11-20T...",
  "updatedAt": "2026-11-20T...",
  "status": "DRAFT"|"VALIDATED"|"BLOCKED"|"OUTDATED",
  "type": "FUNCTIONAL"|"NON_FUNCTIONAL",
  "title": "User can reset password",
  "description": "Registered users can initiate a password reset...",
  "priority": "must"|"should"|"could",
  "acceptanceCriteria": [
    {
      "id": "REQ-F-001-AC1",
      "description": "Email is sent to registered address",
      "verification": {
        "type": "test"|"manual"|"review",
        "testPath": "tests/auth/password-reset.test.mjs#reset-sends-email",
        "command": "node --test tests/auth/password-reset.test.mjs"
      }
    }
  ],
  "links": {
    "implements": ["PRD-FR-001"],
    "designedBy": ["ARCH-001"],
    "implementedBy": ["BACKEND-EMAIL-SERVICE"],
    "verifiedBy": ["REQ-F-001-TEST-1", "REQ-F-001-TEST-2"]
  },
  "validation": { ... }
}
```

### Identifiers

- **FUNCTIONAL**: `REQ-F-XXX` (e.g., REQ-F-001)
- **NON_FUNCTIONAL**: `REQ-NF-XXX` (e.g., REQ-NF-001)
- **CRITERIA**: `REQ-F-XXX-ACn` (e.g., REQ-F-001-AC1)

Identifiers are stable and never reused. Deleting a requirement marks it `BLOCKED` but keeps its ID.

### Coverage calculation (deterministic)

A requirement is **COVERED** only when:

1. **All acceptance criteria are TESTED**
   - Each criterion has at least one verification entry
   - Each verification references an existing test file
   - The test command has been executed (evidence exists)

2. **Test result is PASS**
   - Evidence record exists for the test run
   - Evidence status is `PASS`
   - Evidence references the test path/command

3. **Evidence is recent**
   - Evidence timestamp is within retention period
   - Evidence commit matches current branch head (if git)

**Coverage is computed at runtime, never declared.**

### Registry API (proposed CLI)

```bash
# List requirements
aidf requirements list [--type F|NF] [--status DRAFT|VALIDATED]

# Show requirement details
aidf requirements show REQ-F-001 [--json]

# Create requirement
aidf requirements add --id REQ-F-001 --type F --title "..." --desc "..."

# Update requirement
aidf requirements update REQ-F-001 --title "new title"

# Compute coverage
aidf requirements coverage [--json]

# Link to tests
aidf requirements link REQ-F-001 --test tests/auth/reset.test.mjs#reset-sends-email

# Validate registry
aidf requirements validate [--json]
```

## Consequences

### Positive

- Stable requirement identifiers
- Link to design, implementation, tests
- Deterministic coverage (no false PASS)
- Evidence-based validation

### Negative

- More files to manage
- CLI commands required for manipulation

### Neutral

- Registry stored under `.aidf/artefacts/requirements.json`
- Markdown documents remain source of record for PRD/BACKEND

## Status

**proposed** — awaiting human validation before implementation.

## Next steps

- If approved: implement schema, registry management, coverage calculation
- Tests required: identifier stability, link resolution, coverage computation