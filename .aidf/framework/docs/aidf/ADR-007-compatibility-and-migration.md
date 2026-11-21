# ADR-007 — Compatibility and Migration

- Status: **proposed**
- Date: 2026-11-20
- Deciders: AIDF maintainer
- Reference: AIDF 0.1.0 baseline (commit 85f187a)

## Context

AIDF 0.1.0 is already installed in many projects. L1 introduces canonical JSON artefacts. The question is: how do we migrate existing projects without breaking them?

## Decision

**L1 is additive only**. Never modify or delete existing AIDF 0.1.0 files.

### Migration rules

1. **No breaking changes to existing files**
   - Never overwrite `.aidf/docs/*.md`
   - Never delete `.aidf/config.json`
   - Never modify `.aidf/manifest.json`
   - Never delete existing artefacts

2. **New files are added**
   - `.aidf/artefacts/` directory (new)
   - `.aidf/artefacts/requirements.json` (new)
   - `.aidf/artefacts/decisions/` (new)
   - etc.

3. **Existing tests pass**
   - All 44 existing tests must pass
   - No existing test is deleted
   - New tests are additive

4. **Install is idempotent**
   - Re-running `aidf install` never overwrites existing artefacts
   - Only adds missing artefacts

5. **No automatic migration of existing projects**
   - Existing projects stay as-is (Markdown documents only)
   - L1 features are opt-in
   - No automatic conversion of existing documents

### Migration path for new projects

New projects using AIDF 0.2.0:

1. Install AIDF 0.2.0
2. Documents created in `.aidf/docs/`
3. User creates canonical JSON artefacts manually or via CLI
4. Link JSON artefacts to documents
5. Track coverage, tests, evidence

### Migration path for existing projects (optional)

Projects that want L1 features:

1. Install AIDF 0.2.0 (additive, no breaking changes)
2. Run `aidf docs check` (detects nothing to generate yet)
3. Manually create JSON artefacts OR use CLI to create new ones
4. Link JSON artefacts to existing documents
5. Gradually adopt L1 features

### Compatibility matrix

| Feature | AIDF 0.1.0 | AIDF 0.2.0 |
|---|---|---|
| CLI commands | 18 | 18 + 3 new |
| Artefact files | 11 Markdown | 11 Markdown + JSON artefacts |
| Tests | 44 | 44 + new tests |
| JSON artefacts | None | 7+ canonical artefacts |
| Link resolution | No | Yes |
| Coverage computation | No (manual) | Yes (deterministic) |

### Rollback

If L1 implementation needs rollback:

1. **Git ensures reversion** (commit 85f187a baseline)
2. **New files can be deleted**
3. **No existing files were modified**
4. **All existing tests still pass**

### Compatibility verification

- All 44 existing tests pass
- No existing tests deleted
- New tests additive only
- CLI exit codes preserved
- `--json` support maintained
- Existing commands unchanged

## Consequences

### Positive

- Existing projects unaffected
- Migration is opt-in and reversible
- No breaking changes
- Git ensures rollback

### Negative

- Dual file system (Markdown + JSON)
- User must opt-in to L1 features

### Neutral

- New files added under `.aidf/artefacts/`
- Old files remain in `.aidf/docs/`

## Status

**proposed** — awaiting human validation before implementation.

## Next steps

- If approved: implement additive-only changes, preserve all existing tests
- Tests required: install idempotency, no file overwrite, rollback verification