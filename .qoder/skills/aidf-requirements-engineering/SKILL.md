---
name: aidf-requirements-engineering
description: Write precise, testable requirements and acceptance criteria, and trace them to design, tasks and tests. Use when specifying a feature or reviewing requirement quality.
metadata:
  aidf:
    id: requirements-engineering
    version: 1.0.0
    phases: [specification, planning]
    documents: [PRD, APPFLOW, PLAN]
---

# AIDF Requirements Engineering

## Requirement shape

```text
FR-012 — <capability>
Given <context>
When <action>
Then <observable outcome>
And <invariant that must still hold>
Verification: <test id / command / manual review>
```

## Procedure

1. Decompose the feature into independently verifiable behaviours.
2. For each behaviour, write the nominal path, boundary values, invalid input and
   failure path.
3. Define acceptance criteria that a test can observe. Avoid "works correctly".
4. Build a traceability table:

   | Requirement | Design | Task | Test | Evidence |
   |---|---|---|---|---|
   | FR-012 | BACKEND §2 | T-004 | tests/api.test.ts | evidence id |

5. Flag requirements that cannot be verified automatically, and state the manual
   verification procedure.
6. Check for conflicts with `SECURITY.md` and existing `PRD.md` requirements.

## Quality checks

- No ambiguous pronouns ("it", "the system") without a defined referent.
- No hidden implementation choice in a product requirement.
- Every requirement is necessary; every necessary requirement is present.
- Edge cases are explicit, not left to the implementer.

## Output

- Requirements with acceptance criteria.
- Traceability table.
- A list of `NOT_PROVEN` requirements with the reason.
