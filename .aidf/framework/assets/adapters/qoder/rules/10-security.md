# AIDF Security Rule

Security is a blocking concern, not a review step at the end.

## Secrets

- Never open, print, log, commit or copy `.env`, key material, tokens or
  credential stores.
- Never put a secret in a URL, a client bundle, a test fixture or an error page.
- If a task requires a secret, stop and ask the human to provide it through the
  approved mechanism.

## Changes that require extra care

- authentication, authorization, session and permission changes;
- input parsing, file upload, outbound fetch, redirect and template rendering;
- database migration, deletion, retention or encryption;
- dependency addition from an unverified source;
- CI/CD, deployment and infrastructure permissions.

For these, apply `aidf-security-review` and require human approval before the
security gate can pass.

## Non-negotiables

- Server-side authorization for every protected operation.
- Parameterised queries.
- Allowlisted outbound destinations and bounded timeouts.
- Least privilege for tokens, roles and service accounts.
- No automatic execution of downloaded scripts.
- Residual risk is recorded and accepted by a human, never hidden.

AIDF reduces risk; it does not certify the absence of vulnerabilities.
