---
name: aidf-backend-implementation
description: Implement backend services and APIs with explicit contracts, validation, error handling and tests. Use for endpoints, business rules, jobs and integrations.
metadata:
  aidf:
    id: backend-implementation
    version: 1.0.0
    phases: [implementation]
    documents: [BACKEND, SECURITY, CONVENTIONS]
---

# AIDF Backend Implementation

## Procedure

1. Read the existing route/service/repository pattern and follow it.
2. Confirm the contract in `BACKEND.md` or the contract file. If it is missing,
   write it first.
3. Validate input at the boundary with the project's schema library.
4. Implement business logic in the owning service, not in the transport layer.
5. Define the error mapping explicitly: domain error → status/code.
6. Add tests: nominal, validation failure, authorization failure, dependency
   failure.
7. Check idempotency and retries for writes and external calls.
8. Verify no secret or internal detail leaks in responses or logs.

## Security checklist

- [ ] Authorization enforced server-side for the specific object.
- [ ] Input validated and bounded (size, type, range).
- [ ] Queries parameterised.
- [ ] Outbound calls allowlisted and timed out.
- [ ] Sensitive fields excluded from logs.
- [ ] Rate limit considered for sensitive endpoints.

## Output

- Service/API diff.
- Contract update if behaviour changed.
- Test results as evidence.
- Migration/rollback note if data changed.

## Blocking conditions

- Contract ambiguity that changes behaviour.
- Missing authorization model for the resource.
- Unbounded operation that can exhaust resources.
