---
name: aidf-project-context
description: Discover the real project context before any change. Use at session start, when the stack is unknown, or when a task spans several areas. Produces a context manifest of verified facts, documents and skills.
metadata:
  aidf:
    id: project-context
    version: 1.0.0
    phases: [intake, discovery]
    documents: [constitution, CONVENTIONS, TRD]
    rules: [inspect-before-modify, no-assumption-as-fact]
---

# AIDF Project Context

## When to use

- First message of a session in a project that uses AIDF.
- The request mentions an area you have not inspected.
- The stack, test command or deployment model is unclear.
- A previous answer was based on memory rather than the repository.

## Procedure

1. Run `aidf doctor` and `aidf scan --record`. If `aidf` is unavailable, use
   `node .aidf/bin/aidf.mjs`.
2. Read `.aidf/docs/constitution.md` and `.aidf/docs/CONVENTIONS.md`.
3. Run `aidf context --task "<request>" --write .aidf/state/context/latest.md`.
4. Read the selected documents and skills only. Do not load everything.
5. Inspect the files relevant to the request: routes, services, schemas, tests,
   configuration. Cite paths in your answer.
6. Separate:
   - **Verified facts** (with file path or command output);
   - **Assumptions** (labelled, with a way to verify);
   - **Open questions** (with an owner).

## Output

A short context report containing:

- stack and runtime, with evidence;
- test/build commands, with evidence;
- architecture boundaries relevant to the task;
- selected documents and skills, with why;
- risks and unknowns.

## Hard rules

- Never read or print `.env`, keys, tokens or credential stores.
- Never claim a mechanism is active because a file exists. Activation must be
  observed or marked `NOT_PROVEN`.
- Do not propose an architecture change during context discovery.

## Handoff

- Product framing → `aidf-product-analysis`
- Architecture question → `aidf-architecture-review`
- Ready to implement → `aidf-phase-transition` to check the phase gate.
