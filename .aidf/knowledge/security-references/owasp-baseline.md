---
title: Security baseline references
source: AIDF original summary
references:
  - https://owasp.org/www-project-application-security-verification-standard/
  - https://cheatsheetseries.owasp.org/
license: MIT
status: AVAILABLE
retrieved: 2026-10-30
---

# Security Baseline References

This is a review aid, not a certification and not a claim that the application
is free of vulnerabilities. Verify each item against the real deployment.

## Authentication and session

- [ ] Passwords are hashed with a memory-hard algorithm (Argon2id, scrypt, bcrypt).
- [ ] Session tokens are random, stored securely and rotated on privilege change.
- [ ] Logout invalidates the session server-side.
- [ ] Multi-factor authentication is available for sensitive roles.

## Authorization

- [ ] Every server endpoint enforces authorization.
- [ ] Object-level authorization prevents IDOR (the caller owns the object).
- [ ] Deny by default; permissions are explicit.
- [ ] Admin actions are separately protected and audited.

## Input and output

- [ ] All input is validated against a schema at the boundary.
- [ ] SQL uses parameters; no string concatenation.
- [ ] Output is encoded for its context (HTML, attribute, URL, JS).
- [ ] File uploads are type-checked, size-limited and stored outside the web root.
- [ ] Redirects and outbound fetches are allowlisted (SSRF).

## Secrets and configuration

- [ ] No secret in the repository, logs, error pages or client bundle.
- [ ] Secrets come from environment or a secret manager.
- [ ] Debug mode is off in production.
- [ ] Dependencies are locked and audited.

## Data protection

- [ ] Transport encryption is enforced.
- [ ] Sensitive data at rest is encrypted.
- [ ] Backups are encrypted and restore-tested.
- [ ] Personal data has a retention and deletion path.

## Operations

- [ ] Security-relevant events are logged without sensitive payloads.
- [ ] Alerts exist for authentication anomalies and privilege changes.
- [ ] Incident contact and escalation are documented.
- [ ] Residual risks are recorded and accepted by a human.
