---
title: SQL and data rules
source: AIDF original summary
references:
  - https://www.postgresql.org/docs/
license: MIT
status: AVAILABLE
retrieved: 2026-10-30
---

# SQL and Data Rules

## Schema

- Every table has a primary key.
- Foreign keys are enforced by the database, not only by application code.
- Nullability is a deliberate decision; document it when it affects behaviour.
- Use database-level constraints for invariants that must always hold.
- Names are stable and descriptive; avoid abbreviations that require a glossary.

## Migrations

- Migrations are forward-only and versioned; rollback is a separate, tested
  procedure when the database cannot reverse safely.
- A migration that rewrites a large table needs a plan for locks and duration.
- Destructive changes (drop column/table, data rewrite) require a backup and a
  human approval recorded in `.aidf/evidence/`.
- Never edit an already-applied migration in a shared environment.

## Queries

- Parameterise every value; never concatenate user input into SQL.
- Add indexes for real query patterns, not speculatively. Record the query.
- Avoid `SELECT *` in production code; select the columns the code uses.
- Pagination must be stable (order by a unique key).
- Watch N+1 patterns at ORM boundaries; verify with query logs.

## Access

- The application user is not the schema owner and has only the privileges it
  needs.
- Reporting/analytics access is read-only and separate.
- Secrets for database access come from the environment or a secret manager,
  never from committed files.
