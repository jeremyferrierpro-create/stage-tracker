---
name: aidf-phase-transition
description: Evaluate phase entry and exit gates, collect evidence and block transitions that are not demonstrated. Use before moving to another phase or declaring work complete.
metadata:
  aidf:
    id: phase-transition
    version: 1.0.0
    phases: [intake, discovery, specification, planning, implementation, testing, security, release]
    documents: [PLAN, CHANGELOG]
---

# AIDF Phase Transition

## Procedure

1. Show the current state: `aidf workflow status`.
2. Enter the target phase: `aidf workflow enter <phase>`. If the entry gate is
   not `PASS`, stop and report why.
3. Before leaving, run the exit gate:

   ```bash
   aidf validate --phase <phase> --kind exit --allow-exec
   ```

4. Read the gate report. Statuses are:
   - `PASS` — all mandatory checks demonstrated;
   - `FAIL` — a check ran and failed;
   - `BLOCKED` — a mandatory condition cannot be evaluated (missing config,
     contradiction, missing approval);
   - `NOT_PROVEN` — no evidence was produced;
   - `NOT_APPLICABLE` — the check does not apply, with a reason.
5. If the gate is not `PASS`, do not declare the phase complete. Fix the cause or
   record the blocker.
6. On `PASS`, `aidf workflow exit <phase>` records the evidence and advances.
7. Update `PLAN.md` and `CHANGELOG.md` with the real state.

## Rules

- Never edit a gate result by hand.
- Never turn `NOT_PROVEN` into `PASS` because the work "looks done".
- A human approval is required for specification, plan, security and release.
- A blocked gate is a valid outcome; hiding it is not.

## Output

- Gate report with check-level detail.
- Evidence reference.
- Next action: fix, escalate, or proceed.
