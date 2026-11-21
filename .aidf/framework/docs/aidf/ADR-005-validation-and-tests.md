# ADR-005 — Validation and Tests

- Status: **proposed**
- Date: 2026-11-20
- Deciders: AIDF maintainer
- Reference: AIDF 0.1.0 baseline (commit 85f187a)

## Context

AIDF 0.1.0 has 44 tests in `tests/*.test.mjs`. L1 introduces canonical JSON artefacts that need:

1. Schema validation (JSON Schema Draft 2020-12)
2. Link resolution verification
3. Coverage computation tests
4. Markdown generation tests
5. Compatibility with existing tests

## Decision

Maintain separate layers for validation and testing:

### 1. Schema validation (deterministic)

Use JSON Schema Draft 2020-12 for all artefacts.

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "https://aidf.dev/schemas/requirement/v1",
  "title": "Requirement",
  "description": "A structured requirement in AIDF",
  "type": "object",
  "properties": {
    "id": { "type": "string", "pattern": "^REQ-[FN]\\d{3}$" },
    "schemaVersion": { "const": "1.0.0" },
    "createdAt": { "type": "string", "format": "date-time" },
    "updatedAt": { "type": "string", "format": "date-time" },
    "status": { "enum": ["DRAFT", "VALIDATED", "BLOCKED", "OUTDATED"] },
    "type": { "enum": ["FUNCTIONAL", "NON_FUNCTIONAL"] },
    "title": { "type": "string", "minLength": 1 },
    "description": { "type": "string", "minLength": 10 },
    "acceptanceCriteria": {
      "type": "array",
      "items": { "$ref": "#/$defs/criterion" }
    },
    "links": { "$ref": "#/$defs/links" }
  },
  "required": ["id", "schemaVersion", "createdAt", "status", "type", "title", "description"],
  "$defs": {
    "links": { "type": "object", "properties": { ... } },
    "criterion": { "type": "object", "properties": { ... } }
  }
}
```

Validation rules:

- **Schema validation**: JSON Schema_draft 2020-12
- **Link validation**: All `targetId` must reference existing artefacts
- **Type validation**: Enum values must be in allowed list
- **ID stability**: No duplicates, IDs are never reused

### 2. Link resolution (deterministic)

When an artefact references another:

```json
{
  "links": {
    "designedBy": ["DES-001", "DES-002"]
  }
}
```

Verification:

1. Check `DES-001` exists in artefacts directory
2. Check `DES-002` exists in artefacts directory
3. Report missing links as `BLOCKED`
4. Do not accept link if target doesn't exist

### 3. Coverage computation (deterministic)

Coverage is computed from data, not declared:

```json
{
  "coverage": {
    "requirements": {
      "total": 10,
      "covered": 3,
      "untested": 4,
      "testedButNotPassing": 3,
      "details": [
        { "id": "REQ-F-001", "status": "COVERED", "tests": 2 },
        { "id": "REQ-F-002", "status": "UNTESTED", "tests": 0 }
      ]
    }
  }
}
```

Rules:

- **Uncovered**: No linked tests exist
- **Tested but not passing**: Tests exist but evidence is `FAIL` or `NOT_PROVEN`
- **Covered**: All acceptance criteria have passing evidence

### 4. Test suite structure

| Test file | Scope | Status |
|---|---|---|
| `tests/schema*.test.mjs` | JSON Schema validation | **new** |
| `tests/links*.test.mjs` | Link resolution | **new** |
| `tests/coverage*.test.mjs` | Coverage computation | **new** |
| `tests/generation*.test.mjs` | Markdown generation | **new** |
| `tests/requirements*.test.mjs` | Requirement registry | **new** |
| `tests/traceability*.test.mjs` | Traceability chain | **new** |
| Existing tests | CLI, workflow, evidence, etc. | **existing, pass** |

### 5. Compatibility rules

- **Existing tests must pass** (44/44)
- **No existing test is deleted**
- **New tests are additive**
- **CLI commands respect existing exit codes** (0=success, 1=failure/blocked, 2=conflict)

### 6. Validation API (proposed CLI)

```bash
# Validate artefact
aidf validate artefact requirements.json [--json]

# Validate all artefacts
aidf validate all [--json]

# Check link integrity
aidf validate links [--json]

# Compute coverage
aidf validate coverage [--json]
```

## Consequences

### Positive

- Deterministic validation (no LLM or approximation)
- Link integrity enforced
- Coverage is computed, not declared
- Existing tests preserved

### Negative

- More test files to maintain
- Schema evolution required for breaking changes

### Neutral

- Validation is separate from execution
- Tests are additive, never destructive

## Status

**proposed** — awaiting human validation before implementation.

## Next steps

- If approved: implement JSON schemas, validation, link resolution, coverage
- Tests required: schema validation, link resolution, coverage computation, generation reproducibility