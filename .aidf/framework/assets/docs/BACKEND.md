---
doc: BACKEND
status: DRAFT
version: 0.2.0
source: aidf-template
---

# Backend Specification

> **Status: DRAFT.** Contracts must be extracted from real code, OpenAPI files,
> migrations or approved designs. Mark unimplemented contracts `proposed`.

## 1. Services and boundaries

| Service | Responsibility | Owns data | Depends on | Source path |
|---|---|---|---|---|
| `[TO FILL]` | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` |

## 2. API contracts

| ID | Method | Path | Auth | Request | Response | Errors | Status |
|---|---|---|---|---|---|---|---|
| API-001 | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` | existing / proposed |

### Contract rules

- Validation at the boundary: `[TO FILL]`
- Error envelope: `[TO FILL]`
- Idempotency: `[TO FILL]`
- Pagination: `[TO FILL]`
- Versioning and deprecation: `[TO FILL]`

## 3. Data model

| Entity | Key fields | Constraints | Owner | Migration |
|---|---|---|---|---|
| `[TO FILL]` | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` |

### Data rules

- Transactions and consistency: `[TO FILL]`
- Deletion and retention: `[TO FILL]`
- Indexing and query budgets: `[TO FILL]`
- Sensitive fields and encryption: `[TO FILL]` (reference `SECURITY.md`)

## 4. Business logic

| Rule ID | Rule | Inputs | Outputs | Failure behaviour | Test |
|---|---|---|---|---|---|
| BR-001 | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` |

## 5. Background jobs and integrations

| Job / integration | Trigger | Retry policy | Idempotent | Owner | Evidence |
|---|---|---|---|---|---|
| `[TO FILL]` | `[TO FILL]` | `[TO FILL]` | yes/no | `[TO FILL]` | `[TO FILL]` |

## 6. Observability

- Logs: `[TO FILL]`
- Metrics: `[TO FILL]`
- Traces: `[TO FILL]`
- Alerts: `[TO FILL]`

## 7. Validation record

| Field | Value |
|---|---|
| Validated by | `[TO FILL]` |
| Date | `[TO FILL]` |
| Contract tests | `[TO FILL]` |
