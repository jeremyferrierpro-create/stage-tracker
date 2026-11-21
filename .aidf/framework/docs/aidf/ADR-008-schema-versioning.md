# ADR-008 — Schema Versioning

- Status: **proposed**
- Date: 2026-11-20
- Deciders: AIDF maintainer
- Reference: AIDF 0.1.0 baseline (commit 85f187a)

## Context

JSON artefacts use schemas. The question is: how do we version schemas and handle schema evolution?

## Decision

### Schema versioning

Each artefact schema has an independent version:

```json
{
  "$schema": "https://aidf.dev/schemas/requirement/v1",
  "id": "REQ-F-001",
  "schemaVersion": "1.0.0",
  ...
}
```

- **Schema version**: Independent of AIDF version
- **Artefact version**: Stored in `schemaVersion` field
- **AIDF version**: 0.2.0 (framework version)

### Schema location

```text
.aidf/framework/
  schemas/
    requirement/
      v1.json
      v2.json
    architecture/
      v1.json
    decisions/
      v1.json
```

### Schema evolution

When a schema changes:

1. **Create new schema version** (v1 → v2)
2. **Keep old schema** (v1 still supported)
3. **Migration tool** converts v1 → v2 (optional, explicit)
4. **New artefacts use v2**
5. **Old artefacts remain v1** (no automatic migration)

### Migration strategy

```bash
# Dry-run migration
aidf artefacts migrate --dry-run [--from 1.0.0 --to 2.0.0]

# Execute migration
aidf artefacts migrate --to 2.0.0 [--yes]

# Rollback migration
aidf artefacts migrate --rollback [--yes]
```

### Backward compatibility

- **New schema version must be backward compatible**
- **Old AIDF versions can read new artefacts** (ignoring new fields)
- **New AIDF versions can read old artefacts** (applying defaults for missing fields)

### Validation

Each artefact is validated against its `schemaVersion`:

```json
{
  "validation": {
    "schemaVersion": "1.0.0",
    "timestamp": "2026-11-20T...",
    "valid": true,
    "errors": [],
    "warnings": []
  }
}
```

## Consequences

### Positive

- Schema evolution without breaking changes
- Backward compatibility maintained
- Migration is explicit and controlled

### Negative

- More files (schema versions)
- Migration tool required

### Neutral

- Schema versions are independent
- AIDF supports multiple schema versions

## Status

**proposed** — awaiting human validation before implementation.

## Next steps

- If approved: implement schema versioning, migration tool, backward compatibility
- Tests required: schema validation, migration, backward compatibility