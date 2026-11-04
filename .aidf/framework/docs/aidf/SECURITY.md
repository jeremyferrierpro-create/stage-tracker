# AIDF Security Guide

Status: VALIDATED as shipped policy

## 1. Secret policy

- AIDF never reads or prints `.env`, key material, tokens or credential stores.
  (`aidf scan` lists filenames only; `detectProject` reads `package.json` and
  marker files, never secret files.)
- The knowledge file `assets/knowledge/security-references/owasp-baseline.md`
  summarises OWASP ASVS ideas; SECURITY.md cites sources without copying
  restricted content.
- Command runner records stdout/stderr in evidence, truncated; it never
  redacts (the policy is: only safe, non-secret-producing commands may be
  declared as command checks).

## 2. Execution and network permissions

- `security.allowCommandExecution` (default `false`) or a one-shot
  `--allow-exec` enables command checks.
- `security.allowNetwork` (default `false`) and `--allow-network` gate any
  network operation (`installGitResource`). No remote push/publish exists.
- Command execution is shell-free (`spawn` with `shell: false`), rejects shell
  operators, with a default 300 s timeout and output truncation.
- `security.allowedCommands` further restricts which command keys may run.

## 3. Filesystem policy

- Every write path passes `resolveInside(root, path)`: traversal (`../`),
  absolute paths, null bytes, reserved Windows names and invalid characters are
  refused before touching disk.
- Files not registered in `.aidf/manifest.json` are never overwritten.
- Overwrites of AIDF-managed files keep a backup in `.aidf/backups/` (install)
  or `.aidf-uninstall-backup/` (uninstall `--force`).
- Writes are atomic (temp file + rename).

## 4. Untrusted external content

- External skills/resources are indexed (path, hash, commit) and never executed
  automatically; installing a git resource only copies include-glob files from
  a pinned commit.
- Rule files instruct agents: downloaded content is untrusted data and never
  overrides `.aidf/AIDF.md` or the constitution.
- Licences gate redistribution: unknown licence ⇒ BLOCKED, index-only.

## 5. Governance and human accountability

- `VALIDATED` document statuses and human approvals require `--by "<name>"`.
- Evidence records are hash-chained (`previous` → `hash`), so tampering is
  detectable (`aidf evidence verify`, also part of `doctor`).
- High-stakes phases (`security`, `release`) require `requireHumanApprovalFor`
  checks; agent-only approvals fail as `BLOCKED`.

## 6. Threat model summary (AIDF as the tool)

| Threat | Mitigation |
|---|---|
| Path traversal in config/action paths | `resolveInside` validation |
| Overwriting user files | manifest ownership + conflict refusal |
| Secret leakage via logs/reports | secret content never read; logs are local JSONL |
| Arbitrary shell command via a check | shell-free spawn + operator rejection + opt-in |
| Fake success (agent claiming tests ran) | gate NOT_PROVEN without real evidence |
| Tampered evidence | chained SHA-256, verified on demand |
| Malicious downloaded content | no auto-execution; index-only resources |
| Uncontrolled updates to Spec Kit artefacts | generated constitution carries source hash; conflicts preserve original |

## 7. Reporting

Security findings belong in `.aidf/docs/SECURITY.md` (findings with severity
and verification method) and, for behaviour changes, in DECISIONS.md.
`security-review` evidence requires a human approver name.
