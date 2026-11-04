---
name: aidf-database-design
description: Design or review schemas, migrations, indexes and data access with attention to integrity, performance and reversibility. Use for any persistent data change.
metadata:
  aidf:
    id: database-design
    version: 1.0.0
    phases: [planning, implementation]
    documents: [BACKEND, SECURITY, TRD]
---

# AIDF Database Design

## Procedure

1. Read the current schema and migrations. Do not design from memory.
2. Model entities, keys, relationships and invariants.
3. Decide nullability and defaults explicitly.
4. Write the migration with:
   - forward path;
   - lock/duration estimate for large tables;
   - backfill strategy;
   - rollback or compensating procedure;
   - verification query.
5. Add indexes for real access patterns, with the query that justifies each one.
6. Review access privileges: the application role gets only what it needs.
7. Test on a disposable database: apply, verify, roll back.

## Safety rules

- Destructive changes require a backup and a human approval recorded in
  `.aidf/evidence/`.
- Never edit an applied migration in a shared environment.
- Never run a migration against production from an agent session.
- Parameterise queries; never concatenate input.

## Output

- Schema/migration diff.
- Rollback procedure.
- Verification queries and their results.
- Residual risks (locks, downtime, data loss).

## Blocking conditions

- No backup path for a destructive change.
- A data ownership conflict with another service.
- An unverified assumption about production data volume.
