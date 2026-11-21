# ADR-004 — Markdown Generation and Divergence Detection

- Status: **proposed**
- Date: 2026-11-20
- Deciders: AIDF maintainer
- Reference: AIDF 0.1.0 baseline (commit 85f187a)

## Context

AIDF 0.1.0 has 11 Markdown documents in `.aidf/docs/`:

- PRD.md, TRD.md, UI-UX.md, SECURITY.md, BACKEND.md
- APPFLOW.md, PLAN.md, DECISIONS.md, CHANGELOG.md, CONVENTIONS.md, constitution.md

These documents are:

- Currently the source of truth (no canonical JSON layer)
- Human-edited (no generation mechanism)
- Not linked to requirements or tests

L1 introduces canonical JSON artefacts. The question is: how do we handle existing Markdown documents?

## Decision

Preserve existing Markdown documents and treat them as the **canonical source** for now. L1 artefacts will be **derived** from them.

### Migration strategy

1. **Read existing Markdown documents** (`.aidf/docs/*.md`)
2. **Extract structured data** from frontmatter and body
3. **Generate canonical JSON artefacts** (`.aidf/artefacts/requirements.json`, etc.)
4. **Record source metadata** (original file, hash, generation timestamp)
5. **Detect divergence** if Markdown is modified after generation

### Divergence detection

Each canonical artefact stores:

```json
{
  "source": {
    "type": "markdown",
    "path": ".aidf/docs/PRD.md",
    "hash": "sha256-...",
    "generatedAt": "2026-11-20T...",
    "generator": "requirements-schema@1.0.0"
  }
}
```

If a Markdown file is edited manually after generation:

```json
{
  "source": {
    "type": "markdown",
    "path": ".aidf/docs/PRD.md",
    "hash": "sha256-old...",
    "generatedAt": "2026-11-20T...",
    "generator": "requirements-schema@1.0.0"
  },
  "divergence": {
    "detected": true,
    "reason": "Source Markdown hash changed (PRD.md is now sha256-new...)",
    "recommendation": "Manually reconcile or regenerate (overwrites manual changes)"
  }
}
```

### Frozen documents

Documents marked with `frozen: true` in their frontmatter are **protected**:

```markdown
---
doc: constitution
status: DRAFT
frozen: true
---
```

Rules for frozen documents:

- **Never** regenerate automatically
- **Never** overwrite silently
- **Never** modify frontmatter without explicit permission
- Any reconciliation requires human approval

### Proposed CLI for Markdown management

```bash
# List Markdown documents with status
aidf docs list [--json]

# Generate JSON from Markdown
aidf docs generate PRD [--json]

# Detect divergences
aidf docs check [--json]

# Regenerate (overwrites manual changes)
aidf docs regenerate PRD [--yes]

# Reconcile frozen document (requires approval)
aidf docs reconcile PRD --by "Jeremy"
```

## Consequences

### Positive

- Existing Markdown documents remain usable
- Generation is deterministic and reproducible
- Divergence is detected, never hidden
- Frozen documents are protected

### Negative

- Initial migration adds files
- CLI commands required for generation

### Neutral

- Markdown documents remain in `.aidf/docs/`
- Canonical JSON artefacts stored in `.aidf/artefacts/`

## Status

**proposed** — awaiting human validation before implementation.

## Next steps

- If approved: implement Markdown parsing, JSON generation, divergence detection
- Tests required: generation reproducibility, divergence detection, frozen protection