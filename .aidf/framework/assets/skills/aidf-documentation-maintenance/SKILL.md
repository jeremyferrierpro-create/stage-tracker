---
name: aidf-documentation-maintenance
description: Keep AIDF documents, decisions, changelog and traceability accurate after a change. Use at the end of every phase and when documentation drifts from code.
metadata:
  aidf:
    id: documentation-maintenance
    version: 1.0.0
    phases: [release, implementation]
    documents: [CHANGELOG, DECISIONS, PRD, TRD]
---

# AIDF Documentation Maintenance

## Procedure

1. Compare the change with the documents it affects. Use the diff, not memory.
2. Update only what changed; do not rewrite stable sections.
3. For a behaviour change: update `PRD.md`/`APPFLOW.md`, then `TRD.md`/`BACKEND.md`
   if the design changed.
4. For a decision: add or supersede an ADR in `DECISIONS.md`.
5. Add a `CHANGELOG.md` entry under `[Unreleased]`.
6. Update the status:
   - `DRAFT` if not reviewed;
   - `VALIDATED` only with a named human validator and evidence;
   - `OUTDATED` when the document no longer matches reality;
   - `BLOCKED` when a decision is missing;
   - `NOT_APPLICABLE` with a reason.
7. Run `aidf validate --phase release --kind exit` to detect drift.

## Quality checks

- No claim without evidence or a labelled assumption.
- No duplicated source of truth with Spec Kit.
- Links and paths are real.
- Dates and versions are real, not placeholders.

## Output

- Document diff.
- Changelog entry.
- Updated statuses with validation records.
