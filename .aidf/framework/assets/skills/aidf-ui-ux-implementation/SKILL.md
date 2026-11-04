---
name: aidf-ui-ux-implementation
description: Translate flows and design intent into interface structure, states and copy while preserving the existing product surface. Use before frontend coding.
metadata:
  aidf:
    id: ui-ux-implementation
    version: 1.0.0
    phases: [specification, planning]
    documents: [UI-UX, APPFLOW]
---

# AIDF UI / UX Implementation

## Procedure

1. Map each flow in `APPFLOW.md` to screens and transitions.
2. For each screen, define: purpose, entry conditions, primary action,
   secondary actions, exit paths.
3. Define all states: loading, empty, partial, error, success, disabled,
   permission-denied.
4. Write microcopy for errors and empty states: what happened, what to do next.
5. Specify responsive behaviour and keyboard/focus order.
6. Check the design system: reuse tokens and components; list gaps explicitly.
7. Hand off to frontend implementation with a state matrix.

## State matrix

| Screen | Loading | Empty | Error | Success | Permission |
|---|---|---|---|---|---|
| `[screen]` | `[spec]` | `[spec]` | `[spec]` | `[spec]` | `[spec]` |

## Rules

- Never replace the project's existing cockpit or navigation model.
- Never invent a new visual language when one exists.
- Accessibility is part of the specification, not a later task.
- A flow without an error path is incomplete.

## Output

- Updated `UI-UX.md` and `APPFLOW.md`.
- State matrix and interaction notes.
- Open design decisions with an owner.
