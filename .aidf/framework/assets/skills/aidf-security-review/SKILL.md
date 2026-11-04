---
name: aidf-security-review
description: Review a change for security and privacy risks using a threat-informed checklist and record findings with severity and evidence. Use before release and for any auth, data or input change.
metadata:
  aidf:
    id: security-review
    version: 1.0.0
    phases: [security, release]
    documents: [SECURITY, BACKEND, DECISIONS]
---

# AIDF Security Review

## Scope

Review the actual diff and the affected trust boundaries. Do not review from the
description alone.

## Procedure

1. Identify assets, actors and trust boundaries touched by the change.
2. For each boundary, check: authentication, authorization, input validation,
   output encoding, rate limiting, logging.
3. Check secrets: no secret in code, config, logs, errors, client bundle or test
   fixtures.
4. Check dependencies added: origin, licence, maintenance, known advisories.
5. Check data: classification, retention, deletion, encryption, backup.
6. Run the configured audit command if one exists. If not, record
   `NOT_APPLICABLE` with a reason.
7. Record findings:

   | ID | Finding | Severity | Evidence | Mitigation | Status |
   |---|---|---|---|---|---|

8. Record residual risk and require human acceptance for anything not fixed.

## Severity

- **Critical**: exploitable now, high impact (RCE, auth bypass, secret leak).
- **High**: likely exploitable, significant impact.
- **Medium**: requires conditions or has limited impact.
- **Low**: hardening.

A high or critical finding blocks the security gate until fixed or explicitly
accepted by a human with a recorded rationale.

## Hard limits

- AIDF does not certify the absence of vulnerabilities.
- A static checklist is not a penetration test.
- A passing scanner is not proof of security.

## Output

- Findings with severity and evidence.
- Residual risks with acceptance.
- Human approval evidence in `.aidf/evidence/`.
