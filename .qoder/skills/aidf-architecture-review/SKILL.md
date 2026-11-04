---
name: aidf-architecture-review
description: Review or document architecture against real code and constraints, including boundaries, data ownership, failure modes and trade-offs. Use before structural changes or when the TRD is stale.
metadata:
  aidf:
    id: architecture-review
    version: 1.0.0
    phases: [discovery, planning]
    documents: [TRD, DECISIONS, CONVENTIONS]
---

# AIDF Architecture Review

## Procedure

1. Read the real structure: top-level directories, entry points, module
   boundaries, dependency graph, data stores, deployment config.
2. Map components to responsibilities and data ownership. Every claim cites a
   path.
3. Identify:
   - boundaries that leak;
   - circular or hidden dependencies;
   - shared mutable state;
   - single points of failure;
   - unbounded operations.
4. Compare the observed architecture with `TRD.md`. If they differ, mark the TRD
   `OUTDATED` and record the discrepancy.
5. For a proposed change, list at least two options with trade-offs and write an
   ADR in `DECISIONS.md` when a decision is required.
6. Do not propose a rewrite when a bounded change solves the problem.

## Review checklist

- [ ] Data ownership is explicit for each store.
- [ ] Failure modes are handled at the boundary.
- [ ] Timeouts, retries and idempotency are defined for remote calls.
- [ ] The change respects the existing product surface and cockpit.
- [ ] Migration and rollback are possible.

## Output

- Findings with severity and file references.
- ADR(s) for decisions.
- A recommendation that distinguishes "must fix now" from "record and defer".

## Escalation

A contradiction affecting architecture, data ownership or business behaviour is
`BLOCKED` until a human decides. Record it; do not pick silently.
