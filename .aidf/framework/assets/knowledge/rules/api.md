---
title: API design rules
source: AIDF original summary
references:
  - https://www.rfc-editor.org/rfc/rfc9110
license: MIT
status: AVAILABLE
retrieved: 2026-10-30
---

# API Design Rules

## Contracts

- Define the contract before implementation (OpenAPI, schema, or typed
  interface) and keep it in the repository.
- Validate input at the boundary. Reject unknown fields when the contract is
  closed.
- Use consistent error envelopes with a stable machine-readable code, a human
  message, and optional details.
- Never leak stack traces, SQL, internal hostnames or secrets in errors.

## HTTP semantics

- Use the method that matches the semantics; do not tunnel everything through
  POST.
- Status codes: 2xx success, 4xx caller error, 5xx server error. Do not return
  200 with an error payload.
- `PUT` and `DELETE` should be idempotent where the resource model allows it.
- Pagination: cursor-based for large or changing collections; document limits.

## Security

- Authenticate and authorise on the server for every request. UI checks are not
  authorisation.
- Apply rate limits to unauthenticated and sensitive endpoints.
- Do not accept secrets in URLs.
- CORS is explicit; no wildcard with credentials.

## Versioning and change

- Additive changes first. Breaking changes need a version or a deprecation
  window.
- Deprecations have an owner, a date and a migration note.
- Contract tests run in CI.
