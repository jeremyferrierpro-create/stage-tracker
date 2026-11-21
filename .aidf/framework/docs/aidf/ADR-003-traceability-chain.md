# ADR-003 — Traceability Chain

- Status: **proposed**
- Date: 2026-11-20
- Deciders: AIDF maintainer
- Reference: AIDF 0.1.0 baseline (commit 85f187a)

## Context

AIDF 0.1.0 has no mechanism to verify that:

- A requirement has design documentation
- Design documentation is implemented
- Implementation is tested
- Tests have been executed and passed
- Evidence is linked to the requirement

The project needs a traceability chain that:

1. Connects requirement → design → implementation → test → evidence
2. Verifies each link exists and is valid
3. Reports breaks in the chain
4. Computes overall coverage deterministically

## Decision

Define explicit link types between artefacts:

| Link type | Source | Target | Meaning |
|---|---|---|---|
| `implements` | requirement | requirement/decision | Requirement implements a higher-level goal |
| `designed-by` | requirement/design | architecture/design | Requirement/feature has a design |
| `implemented-by` | design | implementation | Design has implementation (file/function) |
| `verified-by` | requirement/test | test | Requirement/test has verification |
| `evidenced-by` | test/evidence | evidence | Test execution has evidence |

### Chain structure

```json
{
  "requirement": {
    "id": "REQ-F-001",
    "links": {
      "implements": ["PRD-001"],
      "designedBy": ["DES-001"],
      "verifiedBy": ["REQ-F-001-TEST-1"]
    }
  },
  "design": {
    "id": "DES-001",
    "links": {
      "designedBy": ["REQ-F-001"],
      "implementedBy": ["BACKEND-001"]
    }
  },
  "implementation": {
    "id": "BACKEND-001",
    "links": {
      "implementedBy": ["DES-001"],
      "verifiedBy": ["REQ-F-001-TEST-1"]
    }
  },
  "test": {
    "id": "REQ-F-001-TEST-1",
    "links": {
      "verifiedBy": ["REQ-F-001", "BACKEND-001"],
      "evidencedBy": ["EVID-001"]
    }
  },
  "evidence": {
    "id": "EVID-001",
    "links": {
      "evidencedBy": ["REQ-F-001-TEST-1"],
      "requirement": "REQ-F-001"
    }
  }
}
```

### Verification API (proposed CLI)

```bash
# Verify requirement chain
aidf traceability verify REQ-F-001 [--json]

# Report all broken chains
aidf traceability report [--json]

# Find requirements without design
aidf traceability missing --type design [--json]

# Find tests without evidence
aidf traceability missing --type evidence [--json]
```

### Coverage computation

```text
coverage = {
  requirements: {
    total: 10,
    covered: 3,
    untested: 4,
    testedButNotPassing: 3
  },
  design: {
    total: 5,
    implemented: 2,
    notImplemented: 3
  },
  tests: {
    total: 6,
    executed: 3,
    passing: 1,
    failing: 2
  }
}
```

## Consequences

### Positive

- End-to-end traceability
- Break detection (requirement without test, test without evidence)
- Deterministic coverage computation

### Negative

- More files to manage
- Link validation required

### Neutral

- Links are explicit, never inferred
- Missing links are reported, not assumed

## Status

**proposed** — awaiting human validation before implementation.

## Next steps

- If approved: implement link types, verification, coverage computation
- Tests required: chain verification, break detection, coverage calculation