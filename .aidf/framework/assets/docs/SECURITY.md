---
doc: SECURITY
status: DRAFT
version: 0.2.0
source: aidf-template
---

# Security Specification

> **Status: DRAFT.** This is a working security baseline, not a certification.
> No absence of vulnerabilities is claimed. Validate each control against the
> real deployment.

## 1. Assets and data classification

| Asset | Classification | Owner | Storage | Retention | Notes |
|---|---|---|---|---|---|
| `[TO FILL]` | public / internal / confidential / restricted | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` |

## 2. Trust boundaries and threat model

```text
[TO FILL: users -> edge -> app -> services -> data, with trust boundaries]
```

| ID | Threat | STRIDE | Asset | Likelihood | Impact | Mitigation | Status |
|---|---|---|---|---|---|---|---|
| T-001 | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` | low/med/high | low/med/high | `[TO FILL]` | open |

## 3. Authentication and authorization

- Authentication mechanism: `[TO FILL]`
- Session/token lifetime and rotation: `[TO FILL]`
- Authorization model (RBAC/ABAC/ownership): `[TO FILL]`
- Server-side enforcement points: `[TO FILL]`
- Deny-by-default behaviour: `[TO FILL]`

## 4. Input, output and data protection

| Control | Implementation | Verification | Status |
|---|---|---|---|
| Input validation | `[TO FILL]` | tests | NOT_PROVEN |
| Output encoding | `[TO FILL]` | tests/review | NOT_PROVEN |
| SQL/NoSQL injection prevention | `[TO FILL]` | tests/review | NOT_PROVEN |
| Path traversal prevention | `[TO FILL]` | tests | NOT_PROVEN |
| SSRF protection | `[TO FILL]` | review | NOT_PROVEN |
| File upload controls | `[TO FILL]` | tests | NOT_PROVEN |
| Secrets management | `[TO FILL]` | review | NOT_PROVEN |
| Encryption in transit/at rest | `[TO FILL]` | config review | NOT_PROVEN |

## 5. Dependencies and supply chain

- Lockfile policy: `[TO FILL]`
- Audit command: `[TO FILL: e.g. npm audit --omit=dev]`
- Update policy: `[TO FILL]`
- Provenance requirements for external resources: see `.aidf/registry/resources.lock.json`

## 6. Logging, monitoring and incident response

- Security events logged: `[TO FILL]`
- Sensitive data excluded from logs: `[TO FILL]`
- Alerting: `[TO FILL]`
- Incident contact and escalation: `[TO FILL]`

## 7. Compliance and privacy

| Requirement | Applicability | Implementation | Evidence |
|---|---|---|---|
| `[TO FILL]` | applicable / NOT_APPLICABLE | `[TO FILL]` | `[TO FILL]` |

## 8. Human validation requirements

The following operations require explicit human approval before execution:

- production data migration or deletion;
- credential or permission changes;
- dependency addition from an unverified source;
- release to production;
- `[TO FILL: project-specific]`

## 9. Residual risks

| ID | Risk | Accepted by | Date | Rationale |
|---|---|---|---|---|
| RR-001 | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` | `[TO FILL]` |

## 10. Validation record

| Field | Value |
|---|---|
| Security reviewer | `[TO FILL]` |
| Date | `[TO FILL]` |
| Checks executed | `[TO FILL]` |
| Limitations | `[TO FILL]` |
