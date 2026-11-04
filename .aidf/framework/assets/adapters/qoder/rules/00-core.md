# AIDF Core Operating Rule

This project uses **AIDF** (`.aidf/AIDF.md`). Read it at the start of a session.

## Always

- Inspect the real code, config, tests and git state before changing anything.
- Run `aidf context --task "<request>"` and load only the selected documents and skills.
- Keep document statuses honest: `DRAFT`, `VALIDATED`, `BLOCKED`, `OUTDATED`, `NOT_APPLICABLE`.
- Record evidence before claiming a phase is complete.
- Prefer a small, reviewable change over a rewrite.

## Never

- Never read or print `.env`, tokens, keys or credential stores.
- Never overwrite files not listed in `.aidf/manifest.json`.
- Never execute a downloaded script automatically.
- Never declare a test, integration or security review that did not happen.
- Never replace the project's architecture, cockpit or design system.

## If `aidf` is unavailable

Use `node .aidf/bin/aidf.mjs <command>`.

> Activation note: Qoder rule activation type is configured in the Qoder UI
> (Always Apply / Model Decision / Specific Files / Apply Manually). This file is
> the canonical AIDF rule; set it to **Always Apply** once after installation.
> `AGENTS.md` compatibility is automatic and takes lower precedence than rules.
