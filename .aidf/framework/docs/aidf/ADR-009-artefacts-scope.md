# ADR-009 — Artefacts Scope

- Status: **proposed**
- Date: 2026-11-20
- Deciders: AIDF maintainer
- Reference: AIDF 0.1.0 baseline (commit 85f187a)

## Context

The question is: which artefacts do we include in L1? The answer depends on:

1. What AIDF needs to trace (requirements, decisions, tests, evidence)
2. What makes sense for the first iteration
3. What is minimal but complete

## Decision

### L1 canonical artefacts

| Artefact | File | Description | Priority |
|---|---|---|---|
| discovery | `.aidf/artefacts/discovery.json` | Project facts, constraints, resources | P0 |
| constraints | `.aidf/artefacts/constraints.json` | Non-negotiable limits | P0 |
| requirements | `.aidf/artefacts/requirements.json` | Requirement registry | P0 |
| architecture | `.aidf/artefacts/architecture.json` | Architecture decisions and model | P1 |
| decisions/ | `.aidf/artefacts/decisions/` | ADRs | P1 |
| test-plan | `.aidf/artefacts/test-plan.json` | Test strategy and test map | P1 |
| security/threat-model | `.aidf/artefacts/security/threat-model.json` | STRIDE threat model | P1 |

### Rationale

- **P0 (core)**: discovery, constraints, requirements — minimal required for L1
- **P1 (extended)**: architecture, decisions, test-plan, threat-model — can be added later

### Artefact dependencies

```text
discovery → constraints → requirements → architecture → test-plan
                                  ↓
                            decisions
                                  ↓
                            security/threat-model
```

### Iterative approach

- **L1-A**: Design (ADR-001 to ADR-009)
- **L1-B**: Core artefacts (discovery, constraints, requirements)
- **L1-C**: Extended artefacts (architecture, decisions, test-plan, threat-model)

### Future artefacts (not in L1)

- codeSnippets/ (code examples)
- api-specs/ (OpenAPI, AsyncAPI)
- db-schema/ (database schemas)
- ci-cd/ (pipeline definitions)

These can be added in L2+ without breaking L1.

## Consequences

### Positive

- Minimal artefacts for first iteration
- Clear priority order
- Future artefacts can be added incrementally

### Negative

- Some features delayed to L2+

### Neutral

- artefacts are extensible
- New artefacts can be added without breaking changes

## Status

**proposed** — awaiting human validation before implementation.

## Next steps

- If approved: implement P0 artefacts (discovery, constraints, requirements) in L1-B
- P1 artefacts (architecture, decisions, test-plan, threat-model) deferred to L1-C