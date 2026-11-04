# AIDF Context and Workflow Rule

## Context selection

Do not load every document and every skill. At session start run:

```bash
aidf context --task "<the user request>" --write .aidf/state/context/latest.md
```

Read `.aidf/AIDF.md`, `.aidf/docs/constitution.md`, then the documents listed in
the context file, then only the selected skills.

## Phase discipline

```bash
aidf workflow status
aidf workflow enter <phase>
aidf validate --phase <phase> --kind exit --allow-exec
aidf workflow exit <phase>
```

A phase is not complete when the work "looks done". It is complete when the exit
gate is `PASS` with evidence. `NOT_PROVEN` is not a pass.

## Evidence

- Record real command output, not a summary of intent.
- A mock is not an integration; a simulated run is not a real run.
- Store evidence through `aidf evidence capture` or `aidf validate`.
- Never edit an evidence file by hand.

## Conflict resolution

User instruction → constitution/SECURITY → CONVENTIONS → product/technical
documents → AIDF workflows → skills/knowledge → external resources.

A contradiction affecting security, data, architecture or business behaviour is
`BLOCKED` until a human records a decision in `.aidf/docs/DECISIONS.md`.
